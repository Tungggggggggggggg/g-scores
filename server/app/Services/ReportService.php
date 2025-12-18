<?php

namespace App\Services;

use App\Subjects\SubjectRegistry;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    private const BUCKET_DEFINITIONS = [
        ['label' => '>= 8', 'suffix' => 'ge8', 'min' => 8.0, 'max' => null],
        ['label' => '6 - < 8', 'suffix' => 'ge6_lt8', 'min' => 6.0, 'max' => 8.0],
        ['label' => '4 - < 6', 'suffix' => 'ge4_lt6', 'min' => 4.0, 'max' => 6.0],
        ['label' => '< 4', 'suffix' => 'lt4', 'min' => null, 'max' => 4.0],
    ];

    public function buildDistribution(): array
    {
        return $this->buildAggregatedBuckets();
    }

    private function buildAggregatedBuckets(): array
    {
        $subjects = SubjectRegistry::all();

        $selectExpressions = [];
        foreach ($subjects as $subject) {
            $column = $subject->column();
            foreach (self::BUCKET_DEFINITIONS as $bucket) {
                $condition = $this->buildConditionFromBounds($column, $bucket['min'], $bucket['max']);
                $alias = $column.'_'.$bucket['suffix'];
                $selectExpressions[] = "SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) AS {$alias}";
            }
        }

        $row = DB::table('scores')->selectRaw(implode(', ', $selectExpressions))->first() ?? (object) [];

        $labels = [];
        $buckets = [];
        foreach (self::BUCKET_DEFINITIONS as $bucket) {
            $buckets[$bucket['label']] = [];
        }

        foreach ($subjects as $subject) {
            $column = $subject->column();
            $labels[] = $subject->label();

            foreach (self::BUCKET_DEFINITIONS as $bucket) {
                $alias = $column.'_'.$bucket['suffix'];
                $buckets[$bucket['label']][] = (int) ($row->{$alias} ?? 0);
            }
        }

        return [
            'labels' => $labels,
            'buckets' => $buckets,
        ];
    }

    private function buildConditionFromBounds(string $column, ?float $min, ?float $max): string
    {
        $conditions = [];

        if ($min !== null) {
            $conditions[] = $column.' >= '.$min;
        }

        if ($max !== null) {
            $conditions[] = $column.' < '.$max;
        }

        if ($conditions === []) {
            return '1=0';
        }

        return implode(' AND ', $conditions);
    }
}
