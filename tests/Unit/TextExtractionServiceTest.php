<?php

use App\Services\TextExtractionService;
use Illuminate\Support\Facades\Storage;

it('extracts readable text from a pdf marked as encrypted', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/material.pdf', encryptedPdf());

    $text = app(TextExtractionService::class)->extract('uploads/material.pdf');

    expect($text)->toContain('Networking connects devices and allows data exchange across systems.');
});

it('recovers literal text when a pdf has no usable catalog', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/material.pdf', "%PDF-1.4\n".str_repeat('(Networking connects devices and allows data exchange across systems.) Tj\n', 3));

    $text = app(TextExtractionService::class)->extract('uploads/material.pdf');

    expect($text)->toContain('Networking connects devices and allows data exchange across systems.');
});

it('rejects unreadable raw PDF bytes instead of treating them as source text', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/material.pdf', "%PDF-1.4\n(".str_repeat("\xFF\x00\x91\xA4", 100).") Tj\n");

    expect(fn () => app(TextExtractionService::class)->extract('uploads/material.pdf'))
        ->toThrow(RuntimeException::class, 'PDF could not be read reliably');
});

it('extracts paragraphs and table text from a docx document', function () {
    Storage::fake('local');
    Storage::disk('local')->put(
        'uploads/material.docx',
        docxWithText('Networking connects devices and allows data exchange across systems.')
    );

    $text = app(TextExtractionService::class)->extract('uploads/material.docx');

    expect($text)
        ->toContain('Networking connects devices and allows data exchange across systems.')
        ->toContain('OSI model');
});

it('extracts material from the configured default filesystem disk', function () {
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);
    Storage::disk('s3')->put(
        'uploads/material.txt',
        str_repeat('Networking connects devices and allows data exchange across systems. ', 3)
    );

    $text = app(TextExtractionService::class)->extract('uploads/material.txt');

    expect($text)->toContain('Networking connects devices and allows data exchange across systems.');
});

function docxWithText(string $text): string
{
    $path = tempnam(sys_get_temp_dir(), 'quiz-docx-');

    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary DOCX fixture.');
    }

    $zip = new ZipArchive;
    $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($opened !== true) {
        unlink($path);

        throw new RuntimeException('Unable to create a DOCX fixture.');
    }

    $content = htmlspecialchars(str_repeat($text.' ', 3), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);

    $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>{$content}</w:t></w:r></w:p>
        <w:tbl>
            <w:tr>
                <w:tc><w:p><w:r><w:t>OSI model</w:t></w:r></w:p></w:tc>
            </w:tr>
        </w:tbl>
    </w:body>
</w:document>
XML);

    $zip->close();

    $docx = file_get_contents($path);
    unlink($path);

    if ($docx === false) {
        throw new RuntimeException('Unable to read the DOCX fixture.');
    }

    return $docx;
}

function encryptedPdf(): string
{
    $content = 'Networking connects devices and allows data exchange across systems. ';
    $stream = 'BT /F1 12 Tf 72 720 Td ('.str_repeat($content, 3).") Tj ET\n";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n",
        "4 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}endstream\nendobj\n",
        "5 0 obj\n<< /Filter /Standard /V 1 /Length 40 /R 2 >>\nendobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";

    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size 6 /Root 1 0 R /Encrypt 5 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
}
