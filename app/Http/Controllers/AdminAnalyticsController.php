<?php

namespace App\Http\Controllers;

use App\Models\AssessmentResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $totalAssessments = AssessmentResult::query()->count();

        $uniqueStudents = (int) AssessmentResult::query()
            ->distinct('user_id')
            ->count('user_id');

        $programResults = AssessmentResult::query()
            ->whereNotNull('program_code')
            ->whereNotIn('program_code', ['N/A', 'PENDING']);
        $programResultCount = (clone $programResults)->count();
        $aiGeneratedCount = AssessmentResult::query()
            ->where(function ($query): void {
                $query->whereIn('source', ['openai', 'ollama'])
                    ->orWhere(function ($query): void {
                        $query->where('source', 'profile-match')
                            ->whereIn('result->source', ['openai', 'ollama']);
                    });
            })
            ->count();
        $fallbackCount = AssessmentResult::query()
            ->where(function ($query): void {
                $query->where('source', 'fallback')
                    ->orWhere(function ($query): void {
                        $query->where('source', 'profile-match')
                            ->where('result->source', 'fallback');
                    });
            })
            ->count();

        $aiAvailabilityRate = $totalAssessments > 0
            ? round(($aiGeneratedCount / $totalAssessments) * 100, 1)
            : 0.0;

        $flaggedCount = AssessmentResult::query()
            ->whereNotNull('response_flags')
            ->count();

        $programBreakdown = (clone $programResults)
            ->selectRaw('program_code, program_name, count(*) as total')
            ->groupBy('program_code', 'program_name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($programResultCount) {
                $row->percentage = $programResultCount > 0
                    ? round(($row->total / $programResultCount) * 100, 1)
                    : 0.0;

                return $row;
            });

        $recentResults = AssessmentResult::query()
            ->with('user:id,name,email')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(8);

        return view('admin.analytics', compact(
            'totalAssessments',
            'uniqueStudents',
            'aiGeneratedCount',
            'fallbackCount',
            'aiAvailabilityRate',
            'flaggedCount',
            'programBreakdown',
            'recentResults'
        ));
    }

    /**
     * JSON feed for potential dashboard widgets or future integrations.
     */
    public function programDistribution(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $distribution = AssessmentResult::query()
            ->whereNotNull('program_code')
            ->whereNotIn('program_code', ['N/A', 'PENDING'])
            ->selectRaw('program_code, count(*) as total')
            ->groupBy('program_code')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'programs' => $distribution,
        ]);
    }
}
