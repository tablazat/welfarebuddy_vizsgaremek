<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;

class CalorieIntakeController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate   = $request->query('end_date', '9999-12-31');
        $calories  = $request->user()
            ->calorieIntakes()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();
        return response()->json($calories, 200);
    }

    public function store(Request $request)
    {
        $calorie = $request->user()
            ->calorieIntakes()
            ->create(
                $request->validate([
                    'data'        => ['required', 'integer', 'min:1'],
                    'recorded_at' => ['nullable', 'date'],
                ])
            );

        $calorie->refresh();

        StreakService::update($request->user(), $calorie->recorded_at);

        broadcast(new UploadCreated($request->user()->id, ['type' => 'calorie', 'data' => $calorie]));

        return response()->json($calorie, 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $request->user()->calorieIntakes()->findOrFail($id);
        $record->update($request->validate([
            'data'        => ['required', 'integer', 'min:1'],
            'recorded_at' => ['nullable', 'date'],
        ]));
        return response()->json($record->fresh(), 200);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->calorieIntakes()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
