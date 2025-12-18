<?php

namespace App\Services;

use App\Models\Score;

final class ScoreLookupService
{
    public function findBySbd(string $sbd): ?Score
    {
        return Score::query()->find($sbd);
    }
}
