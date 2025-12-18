<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

use function array_flip;
use function array_key_exists;
use function fclose;
use function fopen;
use function fgetcsv;
use function is_numeric;
use function is_string;
use function number_format;
use function preg_replace;
use function trim;

class ImportScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scores:import {--path= : CSV path (default: storage/app/dataset/diem_thi_thpt_2024.csv)} {--chunk=2000 : Rows per batch upsert} {--skip-if-complete : Skip importing if this dataset has been marked as completed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import diem_thi_thpt_2024.csv into database (streaming + chunk upsert)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pathOption = $this->option('path');

        $csvPath = is_string($pathOption) && trim($pathOption) !== ''
            ? trim($pathOption)
            : storage_path('app/dataset/diem_thi_thpt_2024.csv');

        $chunkOption = $this->option('chunk');
        $chunkSize = (int) (is_string($chunkOption) ? trim($chunkOption) : $chunkOption);
        if ($chunkSize <= 0) {
            $chunkSize = 2000;
        }

        $datasetKey = 'diem_thi_thpt_2024';
        $skipIfComplete = (bool) $this->option('skip-if-complete');

        $lockAcquired = false;
        $lockKey = null;
        $driver = null;

        try {
            if (! Schema::hasTable('scores')) {
                $this->error('Table "scores" does not exist. Did you run migrations?');

                return self::FAILURE;
            }

            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                $lockKey = crc32($datasetKey);
                DB::selectOne('SELECT pg_advisory_lock(?) as locked', [$lockKey]);
                $lockAcquired = true;
            }

            if ($skipIfComplete && Schema::hasTable('dataset_imports')) {
                $state = DB::table('dataset_imports')->where('dataset', $datasetKey)->first();
                if ($state !== null && (($state->status ?? null) === 'completed')) {
                    $this->info('Dataset import is marked as completed. Skipping import.');

                    if ($lockAcquired && $driver === 'pgsql' && $lockKey !== null) {
                        DB::selectOne('SELECT pg_advisory_unlock(?) as unlocked', [$lockKey]);
                        $lockAcquired = false;
                    }

                    return self::SUCCESS;
                }
            }

            if (Schema::hasTable('dataset_imports')) {
                $nowForState = now();
                DB::table('dataset_imports')->upsert([
                    [
                        'dataset' => $datasetKey,
                        'status' => 'running',
                        'started_at' => $nowForState,
                        'completed_at' => null,
                        'last_error' => null,
                        'created_at' => $nowForState,
                        'updated_at' => $nowForState,
                    ],
                ], ['dataset'], ['status', 'started_at', 'completed_at', 'last_error', 'updated_at']);
            }
        } catch (Throwable $e) {
            $this->error('Failed to initialize import state: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $handle = @fopen($csvPath, 'rb');
            if ($handle === false) {
                throw new \RuntimeException("CSV file not found or not readable: {$csvPath}");
            }

            $header = fgetcsv($handle);
            if ($header === false) {
                fclose($handle);
                throw new \RuntimeException('CSV header is missing or invalid.');
            }

            $header = array_map(static fn ($value) => trim((string) $value), $header);
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }

            $indexes = array_flip($header);

            $requiredColumns = [
                'sbd',
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

            foreach ($requiredColumns as $col) {
                if (! array_key_exists($col, $indexes)) {
                    fclose($handle);
                    throw new \RuntimeException("CSV is missing required column: {$col}");
                }
            }

            $buffer = [];
            $processed = 0;
            $imported = 0;
            $skipped = 0;
            $progressEvery = 50000;

            $updateColumns = [
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
                'updated_at',
            ];

            $startedAt = microtime(true);
            $now = now();

            while (($row = fgetcsv($handle)) !== false) {
                $processed++;

                $sbd = $row[$indexes['sbd']] ?? null;
                $sbd = is_string($sbd) ? trim($sbd) : null;
                if ($sbd === null || $sbd === '') {
                    $skipped++;
                    continue;
                }

                $buffer[] = [
                    'sbd' => $sbd,
                    'toan' => $this->parseScore($row[$indexes['toan']] ?? null),
                    'ngu_van' => $this->parseScore($row[$indexes['ngu_van']] ?? null),
                    'ngoai_ngu' => $this->parseScore($row[$indexes['ngoai_ngu']] ?? null),
                    'vat_li' => $this->parseScore($row[$indexes['vat_li']] ?? null),
                    'hoa_hoc' => $this->parseScore($row[$indexes['hoa_hoc']] ?? null),
                    'sinh_hoc' => $this->parseScore($row[$indexes['sinh_hoc']] ?? null),
                    'lich_su' => $this->parseScore($row[$indexes['lich_su']] ?? null),
                    'dia_li' => $this->parseScore($row[$indexes['dia_li']] ?? null),
                    'gdcd' => $this->parseScore($row[$indexes['gdcd']] ?? null),
                    'ma_ngoai_ngu' => $this->parseString($row[$indexes['ma_ngoai_ngu']] ?? null, 10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($buffer) >= $chunkSize) {
                    $imported += $this->flushBuffer($buffer, $updateColumns);
                    $buffer = [];
                }

                if ($processed % $progressEvery === 0) {
                    $this->info("Processed {$processed} rows... (imported: {$imported}, skipped: {$skipped})");
                }
            }

            if (count($buffer) > 0) {
                $imported += $this->flushBuffer($buffer, $updateColumns);
            }

            fclose($handle);

            if (Schema::hasTable('dataset_imports')) {
                try {
                    $nowForState = now();
                    DB::table('dataset_imports')->where('dataset', $datasetKey)->update([
                        'status' => 'completed',
                        'completed_at' => $nowForState,
                        'last_error' => null,
                        'updated_at' => $nowForState,
                    ]);
                } catch (Throwable) {
                }
            }

            Cache::forget('distribution_report');
            Cache::forget('top10_group_a');

            $elapsed = microtime(true) - $startedAt;
            $this->info("Done. processed={$processed}, imported={$imported}, skipped={$skipped}, seconds=".number_format($elapsed, 2, '.', ''));

            return self::SUCCESS;
        } catch (Throwable $e) {
            if (Schema::hasTable('dataset_imports')) {
                try {
                    DB::table('dataset_imports')->where('dataset', $datasetKey)->update([
                        'status' => 'failed',
                        'last_error' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
                } catch (Throwable) {
                }
            }

            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            try {
                if ($lockAcquired && $driver === 'pgsql' && $lockKey !== null) {
                    DB::selectOne('SELECT pg_advisory_unlock(?) as unlocked', [$lockKey]);
                }
            } catch (Throwable) {
            }
        }
    }
    private function flushBuffer(array $buffer, array $updateColumns): int
    {
        DB::table('scores')->upsert($buffer, ['sbd'], $updateColumns);

        return count($buffer);
    }

    private function parseString(mixed $raw, int $maxLen): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (strlen($value) > $maxLen) {
            return substr($value, 0, $maxLen);
        }

        return $value;
    }

    private function parseScore(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw) && trim($raw) === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        if ($value < 0 || $value > 10) {
            return null;
        }

        return number_format($value, 2, '.', '');
    }
}
