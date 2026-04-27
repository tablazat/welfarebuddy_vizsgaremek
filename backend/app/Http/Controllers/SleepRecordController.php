<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;
use Carbon\Carbon;

class SleepRecordController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate   = $request->query('end_date', '9999-12-31');
        $records   = $request->user()
            ->sleepRecords()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();
        return response()->json($records, 200);
    }

    public function lastNight(Request $request)
    {
        $record = $request->user()
            ->sleepRecords()
            ->orderByDesc('recorded_at')
            ->first();
        return response()->json($record, 200);
    }

    public function store(Request $request)
    {
        $record = $request->user()
            ->sleepRecords()
            ->create(
                $request->validate([
                    'hours'       => ['required', 'numeric', 'min:0.25', 'max:24'],
                    'quality'     => ['nullable', 'integer', 'min:1', 'max:5'],
                    'recorded_at' => ['nullable', 'date'],
                ])
            );

        $record->refresh();

        StreakService::update($request->user(), $record->recorded_at);

        broadcast(new UploadCreated($request->user()->id, ['type' => 'sleep', 'data' => $record]));

        return response()->json($record, 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $request->user()->sleepRecords()->findOrFail($id);
        $record->update($request->validate([
            'hours'       => ['required', 'numeric', 'min:0.25', 'max:24'],
            'quality'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'recorded_at' => ['nullable', 'date'],
        ]));
        return response()->json($record->fresh(), 200);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->sleepRecords()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
