<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ExportController extends Controller
{
    public function json(Request $request): Response
    {
        return $this->buildJson($request->user());
    }

    public function adminJson(Request $request, User $user): Response
    {
        return $this->buildJson($user);
    }

    public function csv(Request $request): Response
    {
        return $this->buildCsvZip($request->user());
    }

    public function adminCsv(Request $request, User $user): Response
    {
        return $this->buildCsvZip($user);
    }

    private function buildJson(User $user): Response
    {
        
        $safe = fn(callable $q) => $this->safeGet($q);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'name'       => $user->name,
                'email'      => $user->email,
                'height_cm'  => $user->height_cm,
                'locale'     => $user->locale,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'heart_rates'     => $safe(fn() => $user->heartRates()->orderBy('recorded_at')->get(['heart_rate', 'recorded_at'])),
            'blood_pressures' => $safe(fn() => $user->bloodPressures()->orderBy('recorded_at')->get(['systolic', 'diastolic', 'recorded_at'])),
            'weights'         => $safe(fn() => $user->weights()->orderBy('recorded_at')->get(['weight', 'recorded_at'])),
            'steps'           => $safe(fn() => $user->steps()->orderBy('recorded_at')->get(['steps', 'recorded_at'])),
            'exercises'       => $safe(fn() => $user->activity_users()->with('activity:id,type,name_hu,name_en,name_de')->orderBy('begin')->get(['activity_id', 'begin', 'end'])),
            'calorie_intakes' => $safe(fn() => $user->calorieIntakes()->orderBy('recorded_at')->get(['data', 'recorded_at'])),
            'water_intakes'   => $safe(fn() => $user->waterIntakes()->orderBy('recorded_at')->get(['amount_ml', 'recorded_at'])),
            'sleep_records'   => $safe(fn() => $user->sleepRecords()->orderBy('recorded_at')->get(['hours', 'quality', 'recorded_at'])),
        ];

        $filename = 'welfarebuddy-export-' . $user->id . '-' . now()->format('Y-m-d') . '.json';

        return response(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    private function buildCsvZip(User $user): Response
    {
        $tmpDir  = sys_get_temp_dir();
        $zipPath = $tmpDir . '/wb-export-' . $user->id . '-' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => __('messages.export.archive_failed')], 500);
        }

        $safe   = fn(callable $q) => $this->safeGet($q);
        $locale = in_array($user->locale, ['hu', 'en', 'de'], true) ? $user->locale : 'en';

        $tables = [
            'heart_rates'     => [
                'columns' => ['heart_rate_bpm', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->heartRates()->orderBy('recorded_at')->get(['heart_rate', 'recorded_at']))
                    ->map(fn($r) => [
                        'heart_rate_bpm' => $r->heart_rate,
                        'recorded_at'    => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
            'blood_pressures' => [
                'columns' => ['systolic_mmhg', 'diastolic_mmhg', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->bloodPressures()->orderBy('recorded_at')->get(['systolic', 'diastolic', 'recorded_at']))
                    ->map(fn($r) => [
                        'systolic_mmhg'  => $r->systolic,
                        'diastolic_mmhg' => $r->diastolic,
                        'recorded_at'    => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
            'weights'         => [
                'columns' => ['weight_kg', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->weights()->orderBy('recorded_at')->get(['weight', 'recorded_at']))
                    ->map(fn($r) => [
                        'weight_kg'   => $r->weight,
                        'recorded_at' => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
            'steps'           => [
                'columns' => ['steps_count', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->steps()->orderBy('recorded_at')->get(['steps', 'recorded_at']))
                    ->map(fn($r) => [
                        'steps_count' => $r->steps,
                        'recorded_at' => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
            'exercises'       => [
                'columns' => ['activity', 'begin', 'end', 'duration_minutes'],
                'rows'    => function () use ($user, $locale, $safe) {
                    $nameKey = "name_{$locale}";
                    return $safe(fn() => $user->activity_users()->with('activity:id,type,name_hu,name_en,name_de')->orderBy('begin')->get())
                        ->map(fn($e) => [
                            'activity'         => $e->activity?->{$nameKey}
                                                  ?? $e->activity?->name_en
                                                  ?? $e->activity?->name_hu
                                                  ?? $e->activity?->type
                                                  ?? '',
                            'begin'            => $this->isoOrNull($e->begin),
                            'end'              => $this->isoOrNull($e->end),
                            'duration_minutes' => $e->begin && $e->end
                                ? (int) round(Carbon::parse($e->begin)->diffInMinutes(Carbon::parse($e->end)))
                                : '',
                        ]);
                },
            ],
            'calorie_intakes' => [
                'columns' => ['calories_kcal', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->calorieIntakes()->orderBy('recorded_at')->get(['data', 'recorded_at']))
                    ->map(fn($r) => [
                        'calories_kcal' => $r->data,
                        'recorded_at'   => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
            'water_intakes'   => [
                'columns' => ['water_ml', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->waterIntakes()->orderBy('recorded_at')->get(['amount_ml', 'recorded_at']))
                    ->map(fn($r) => [
                        'water_ml'    => $r->amount_ml,
                        'recorded_at' => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
            'sleep_records'   => [
                'columns' => ['sleep_hours', 'quality_1to5', 'recorded_at'],
                'rows'    => fn() => $safe(fn() => $user->sleepRecords()->orderBy('recorded_at')->get(['hours', 'quality', 'recorded_at']))
                    ->map(fn($r) => [
                        'sleep_hours'  => $r->hours,
                        'quality_1to5' => $r->quality,
                        'recorded_at'  => $this->isoOrNull($r->recorded_at),
                    ]),
            ],
        ];

        foreach ($tables as $tableName => $spec) {
            $rows = $spec['rows']();
            if ($rows->isEmpty()) {
                continue;
            }
            $headers = $this->csvHeaders($spec['columns'], $locale);
            $zip->addFromString("{$tableName}.csv", $this->buildCsv($headers, $rows->toArray()));
        }

        $zip->close();

        $content  = file_get_contents($zipPath);
        @unlink($zipPath);

        $filename = 'welfarebuddy-export-' . $user->id . '-' . now()->format('Y-m-d') . '.zip';

        return response($content, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    private function csvHeaders(array $columnKeys, string $locale): array
    {
        static $map = [
            'heart_rate_bpm'   => ['hu' => 'pulzus_bpm',     'en' => 'heart_rate_bpm', 'de' => 'puls_bpm'],
            'systolic_mmhg'    => ['hu' => 'szisztoles_mmhg','en' => 'systolic_mmhg',  'de' => 'systolisch_mmhg'],
            'diastolic_mmhg'   => ['hu' => 'diasztoles_mmhg','en' => 'diastolic_mmhg', 'de' => 'diastolisch_mmhg'],
            'weight_kg'        => ['hu' => 'suly_kg',        'en' => 'weight_kg',      'de' => 'gewicht_kg'],
            'steps_count'      => ['hu' => 'lepes_db',       'en' => 'steps_count',    'de' => 'schritte_anzahl'],
            'calories_kcal'    => ['hu' => 'kaloria_kcal',   'en' => 'calories_kcal',  'de' => 'kalorien_kcal'],
            'water_ml'         => ['hu' => 'viz_ml',         'en' => 'water_ml',       'de' => 'wasser_ml'],
            'sleep_hours'      => ['hu' => 'alvas_ora',      'en' => 'sleep_hours',    'de' => 'schlaf_stunden'],
            'quality_1to5'     => ['hu' => 'minoseg_1tol5',  'en' => 'quality_1to5',   'de' => 'qualitaet_1bis5'],
            'activity'         => ['hu' => 'sport',          'en' => 'activity',       'de' => 'sport'],
            'begin'            => ['hu' => 'kezdes',         'en' => 'begin',          'de' => 'beginn'],
            'end'              => ['hu' => 'vege',           'en' => 'end',            'de' => 'ende'],
            'duration_minutes' => ['hu' => 'idotartam_perc', 'en' => 'duration_minutes', 'de' => 'dauer_minuten'],
            'recorded_at'      => ['hu' => 'rogzitve',       'en' => 'recorded_at',    'de' => 'erfasst_am'],
        ];

        return array_map(fn($key) => $map[$key][$locale] ?? $key, $columnKeys);
    }

    private function isoOrNull($value): string
    {
        if (!$value) {
            return '';
        }
        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function safeGet(callable $query)
    {
        try {
            return $query();
        } catch (\Throwable $e) {
            Log::warning('Export query failed: ' . $e->getMessage());
            return collect();
        }
    }
}
