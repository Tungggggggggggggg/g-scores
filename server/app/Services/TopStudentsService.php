<?php

namespace App\Services;

use App\Models\Score;
use Illuminate\Support\Collection;

final class TopStudentsService
{
    public function top10GroupA(): Collection
    {
        return Score::query()
            ->whereNotNull('toan')
            ->whereNotNull('vat_li')
            ->whereNotNull('hoa_hoc')
            ->select([
                'sbd',
                'toan',
                'vat_li',
                'hoa_hoc',
            ])
            ->selectRaw('(toan + vat_li + hoa_hoc) as total_score')
            ->orderByDesc('total_score')
            ->orderBy('sbd')
            ->limit(10)
            ->get();
    }
}
