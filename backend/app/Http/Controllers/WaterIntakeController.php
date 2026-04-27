<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;
use Carbon\Carbon;

class WaterIntakeController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate   = $request->query('end_date', '9999-12-31');
        $waters    = $request->user()
            ->waterIntakes()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();
        return response()->json($waters, 200);
    }

    public function today(Request $request)
    {
        $today = Carbon::now()->toDateString();
        $total = (int) $request->user()
            ->waterIntakes()
            ->whereDate('recorded_at', $today)
            ->sum('amount_ml');
        return response()->json(['total_ml' => $total, 'date' => $today]);
    }

    public function store(Request $request)
    {
        $water = $request->user()
            ->waterIntakes()
            ->create(
                $request->validate([
                    'amount_ml'   => ['required', 'integer', 'min:1', 'max:5000'],
                    'recorded_at' => ['nullable', 'date'],
                ])
            );

        $water->refresh();

        StreakService::update($request->user(), $water->recorded_at);

        broadcast(new UploadCreated($request->user()->id, ['type' => 'water', 'data' => $water]));

        return response()->json($water, 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $request->user()->waterIntakes()->findOrFail($id);
        $record->update($request->validate([
            'amount_ml'   => ['required', 'integer', 'min:1', 'max:5000'],
            'recorded_at' => ['nullable', 'date'],
        ]));
        return response()->json($record->fresh(), 200);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->waterIntakes()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
