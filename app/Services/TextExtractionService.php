<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class TextExtractionService
{
    public function extract(string $storedPath): string
    {
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);

        if (! $disk->exists($storedPath)) {
            Log::error('Quiz source material is missing from the configured filesystem.', [
                'disk' => $diskName,
                'path' => $storedPath,
            ]);

            throw new RuntimeException('Uploaded learning material could not be found.');
        }

        $source = $disk->readStream($storedPath);
        if (! is_resource($source)) {
            throw new RuntimeException('Uploaded learning material could not be opened for text extraction.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'quiz-material-');
        if ($tempPath === false) {
            fclose($source);
            throw new RuntimeException('A temporary file could not be created for text extraction.');
        }

        try {
            $destination = fopen($tempPath, 'wb');
            if ($destination === false) {
                throw new RuntimeException('A temporary file could not be opened for text extraction.');
            }

            try {
                if (stream_copy_to_stream($source, $destination) === false) {
                    throw new RuntimeException('Uploaded learning material could not be copied for text extraction.');
                }
            } finally {
                fclose($destination);
            }

            $extension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
            $text = match ($extension) {
                'pdf' => $this->extractPdf($tempPath),
                'docx' => $this->extractDocx($tempPath),
                'txt' => $this->extractTxt($tempPath),
                default => throw new RuntimeException('Unsupported file type: '.$extension),
            };

            $cleanText = $this->clean($text);

            if (mb_strlen($cleanText) < 100) {
                throw new RuntimeException('The uploaded file does not contain enough readable text for quiz generation.');
            }

            return $this->limitForPrompt($cleanText);
        } finally {
            fclose($source);

            if (file_exists($tempPath) && ! unlink($tempPath)) {
                Log::warning('Temporary quiz material could not be removed after text extraction.', [
                    'path' => $tempPath,
                ]);
            }
        }
    }

    private function extractPdf(string $fullPath): string
    {
        try {
            $config = new Config;
            $config->setIgnoreEncryption(true);

            return (new Parser([], $config))->parseFile($fullPath)->getText();
        } catch (Throwable $exception) {
            $fallbackText = $this->extractPdfLiteralStrings($fullPath);

            if ($this->isReadablePdfFallback($fallbackText)) {
                return $fallbackText;
            }

            $message = $exception->getMessage();

            if (str_contains($message, 'Secured pdf')) {
                throw new RuntimeException('This PDF is secured or password-protected and its text could not be read. Please upload an unlocked PDF, DOCX, or TXT file.', previous: $exception);
            }

            if (str_contains($message, 'Missing catalog')) {
                throw new RuntimeException('This PDF could not be read reliably. Please re-save or print it as an unlocked PDF, or upload the material as DOCX or TXT.', previous: $exception);
            }

            throw new RuntimeException('This PDF could not be read reliably. Text extraction failed: '.$message, previous: $exception);
        }
    }

    private function isReadablePdfFallback(string $text): bool
    {
        if ($text === '' || preg_match('//u', $text) !== 1) {
            return false;
        }

        $characterCount = mb_strlen($text);
        $wordCount = preg_match_all('/[\p{L}\p{N}]{2,}/u', $text);
        $letterCount = preg_match_all('/[\p{L}]/u', $text);

        return $characterCount >= 100
            && $wordCount !== false
            && $wordCount >= 20
            && $letterCount !== false
            && ($letterCount / $characterCount) >= 0.45;
    }

    private function extractPdfLiteralStrings(string $fullPath): string
    {
        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            return '';
        }

        preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $contents, $matches);

        $strings = array_map(function (string $value): string {
            $value = substr($value, 1, -1);
            $value = preg_replace_callback('/\\\\([\\\\()nrtbf])/', function (array $match): string {
                return match ($match[1]) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\b",
                    'f' => "\f",
                    default => $match[1],
                };
            }, $value) ?? $value;

            return trim($value);
        }, $matches[0] ?? []);

        return implode("\n", array_filter($strings, fn (string $value): bool => $value !== ''));
    }

    private function extractDocx(string $fullPath): string
    {
        try {
            $document = IOFactory::load($fullPath);
            $lines = [];

            foreach ($document->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $lines = array_merge($lines, $this->extractPhpWordElementText($element));
                }
            }

            return implode("\n", $lines);
        } catch (Throwable $exception) {
            throw new RuntimeException('DOCX text extraction failed: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function extractTxt(string $fullPath): string
    {
        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new RuntimeException('TXT file could not be read.');
        }

        return $contents;
    }

    /**
     * PHPWord has several element types. This small recursive reader keeps the
     * service independent from a single document layout style.
     *
     * @return array<int, string>
     */
    private function extractPhpWordElementText(object $element): array
    {
        $lines = [];

        if (method_exists($element, 'getText')) {
            $text = $element->getText();

            if (is_string($text) && trim($text) !== '') {
                $lines[] = $text;
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $lines = array_merge($lines, $this->extractPhpWordElementText($childElement));
            }
        }

        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        $lines = array_merge($lines, $this->extractPhpWordElementText($cellElement));
                    }
                }
            }
        }

        return $lines;
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\P{C}\t\r\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([,.;:!?])/u', '$1', $text) ?? $text;
        $text = preg_replace("/(\r?\n){3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function limitForPrompt(string $text): string
    {
        $limit = (int) config('services.openai.max_document_chars', 12000);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit);
    }
}
