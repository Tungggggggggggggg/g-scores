<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\ScoreLookupService;
use App\Services\TopStudentsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    private const LOOKUP_FIELDS = [
        'toan',
        'ngu_van',
        'ngoai_ngu',
        'vat_li',
        'hoa_hoc',
        'sinh_hoc',
        'lich_su',
        'dia_li',
        'gdcd',
        'ma_ngoai_ngu',
    ];

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'sbd' => ['required', 'regex:/^\d{8}$/'],
        ]);

        return redirect()->route('home', ['sbd' => $validated['sbd']]);
    }

    public function lookupJson(Request $request, ScoreLookupService $scoreLookupService)
    {
        if (! $this->isDatasetReady()) {
            return response()->json([
                'error' => true,
                'message' => 'Dữ liệu đang được import. Vui lòng thử lại sau.',
                'details' => null,
            ], 503);
        }

        $sbd = $this->normalizeSbd($request->input('sbd'));

        if (! $this->isValidSbd($sbd)) {
            return response()->json([
                'error' => true,
                'message' => 'Số báo danh không hợp lệ. Vui lòng nhập đúng 8 chữ số.',
                'details' => null,
            ], 422);
        }

        $score = $scoreLookupService->findBySbd($sbd);
        if ($score === null) {
            return response()->json([
                'error' => true,
                'message' => "Không tìm thấy thí sinh với SBD {$sbd}.",
                'details' => null,
            ], 404);
        }

        $details = ['sbd' => $score->sbd];
        foreach (self::LOOKUP_FIELDS as $field) {
            $details[$field] = $score->{$field};
        }

        return response()->json([
            'error' => false,
            'message' => 'OK',
            'details' => $details,
        ]);
    }

    public function index(Request $request, ScoreLookupService $scoreLookupService, TopStudentsService $topStudentsService, ReportService $reportService)
    {
        $datasetNotReady = ! $this->isDatasetReady();
        $sbd = $this->normalizeSbd($request->query('sbd'));
        $examResult = null;
        $lookupError = null;

        if (! $datasetNotReady && $sbd !== '') {
            if (! $this->isValidSbd($sbd)) {
                $lookupError = 'Số báo danh không hợp lệ. Vui lòng nhập đúng 8 chữ số.';
            } else {
                $examResult = $scoreLookupService->findBySbd($sbd);
                if ($examResult === null) {
                    $lookupError = "Không tìm thấy thí sinh với SBD {$sbd}.";
                }
            }
        }

        $distribution = null;
        $top10GroupA = [];
        if (! $datasetNotReady) {
            $distribution = Cache::remember('distribution_report', 600, fn () => $reportService->buildDistribution());
            $top10GroupA = Cache::remember('top10_group_a', 600, function () use ($topStudentsService) {
                return $topStudentsService->top10GroupA()
                    ->map(fn ($row) => [
                        'sbd' => $row->sbd,
                        'toan' => $row->toan,
                        'vat_li' => $row->vat_li,
                        'hoa_hoc' => $row->hoa_hoc,
                        'total' => $row->total_score,
                    ])
                    ->values()
                    ->all();
            });
        }

        return view('pages.home', [
            'sbd' => $sbd !== '' ? $sbd : null,
            'examResult' => $examResult,
            'lookupError' => $lookupError,
            'distribution' => $distribution,
            'top10GroupA' => $top10GroupA,
            'datasetNotReady' => $datasetNotReady,
        ]);
    }

    private function isDatasetReady(): bool
    {
        try {
            if (! Schema::hasTable('dataset_imports')) {
                return true;
            }

            $state = DB::table('dataset_imports')->where('dataset', 'diem_thi_thpt_2024')->first();
            if ($state === null) {
                return false;
            }

            return ($state->status ?? null) === 'completed';
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeSbd(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        return trim($raw);
    }

    private function isValidSbd(string $sbd): bool
    {
        return $sbd !== '' && preg_match('/^\d{8}$/', $sbd) === 1;
    }
}
