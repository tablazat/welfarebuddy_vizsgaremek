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
        // Ha valamelyik tábla/migration hiányzik prodon, ne boruljon az egész export
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
            return response()->json(['message' => 'Failed to create export archive'], 500);
        }

        $safe = fn(callable $q) => $this->safeGet($q);

        $zip->addFromString('heart_rates.csv', $this->buildCsv(
            ['heart_rate', 'recorded_at'],
            $safe(fn() => $user->heartRates()->orderBy('recorded_at')->get(['heart_rate', 'recorded_at']))->toArray()
        ));

        $zip->addFromString('blood_pressures.csv', $this->buildCsv(
            ['systolic', 'diastolic', 'recorded_at'],
            $safe(fn() => $user->bloodPressures()->orderBy('recorded_at')->get(['systolic', 'diastolic', 'recorded_at']))->toArray()
        ));

        $zip->addFromString('weights.csv', $this->buildCsv(
            ['weight_kg', 'recorded_at'],
            $safe(fn() => $user->weights()->orderBy('recorded_at')->get(['weight', 'recorded_at'])
                ->map(fn($w) => ['weight_kg' => $w->weight, 'recorded_at' => $w->recorded_at]))->toArray()
        ));

        $zip->addFromString('steps.csv', $this->buildCsv(
            ['steps', 'recorded_at'],
            $safe(fn() => $user->steps()->orderBy('recorded_at')->get(['steps', 'recorded_at']))->toArray()
        ));

        // Activity név locale szerint, fallback: en → hu → type slug
        $locale  = in_array($user->locale, ['hu', 'en', 'de'], true) ? $user->locale : 'en';
        $nameKey = "name_{$locale}";

        $zip->addFromString('exercises.csv', $this->buildCsv(
            ['activity', 'begin', 'end', 'duration_minutes'],
            $safe(fn() => $user->activity_users()->with('activity:id,type,name_hu,name_en,name_de')->orderBy('begin')->get()
                ->map(fn($e) => [
                    'activity'         => $e->activity?->{$nameKey}
                                          ?? $e->activity?->name_en
                                          ?? $e->activity?->name_hu
                                          ?? $e->activity?->type
                                          ?? '',
                    'begin'            => $e->begin,
                    'end'              => $e->end,
                    'duration_minutes' => $e->begin && $e->end
                        ? (int) round(Carbon::parse($e->begin)->diffInMinutes(Carbon::parse($e->end)))
                        : '',
                ]))->toArray()
        ));

        $zip->addFromString('calorie_intakes.csv', $this->buildCsv(
            ['calories_kcal', 'recorded_at'],
            $safe(fn() => $user->calorieIntakes()->orderBy('recorded_at')->get(['data', 'recorded_at'])
                ->map(fn($c) => ['calories_kcal' => $c->data, 'recorded_at' => $c->recorded_at]))->toArray()
        ));

        $zip->addFromString('water_intakes.csv', $this->buildCsv(
            ['amount_ml', 'recorded_at'],
            $safe(fn() => $user->waterIntakes()->orderBy('recorded_at')->get(['amount_ml', 'recorded_at']))->toArray()
        ));

        $zip->addFromString('sleep_records.csv', $this->buildCsv(
            ['hours', 'quality', 'recorded_at'],
            $safe(fn() => $user->sleepRecords()->orderBy('recorded_at')->get(['hours', 'quality', 'recorded_at']))->toArray()
        ));

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

    /**
     * Ha egy reláció táblája hiányzik (nincs lefuttatva a migration) vagy a
     * lekérdezés más okból elszáll, üres collection-nel folytatjuk, hogy az
     * export ne hasaljon el teljesen. A hibát logoljuk.
     */
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
