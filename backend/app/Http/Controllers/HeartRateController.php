<?php

namespace App\Http\Controllers;

use App\Models\HeartRate;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;

class HeartRateController extends Controller
{
    public function index(Request $request){
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate = $request->query('end_date', '9999-12-31');
        $heartrates = $request->user()->heartRates()->whereBetween('recorded_at', [$startDate, $endDate])->orderBy('recorded_at')->get();
        return response()->json($heartrates,200);
    }

    public function average(Request $request){
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate = $request->query('end_date', '9999-12-31');
        $heartrates = $request->user()->heartRates()->whereBetween('recorded_at', [$startDate, $endDate])->avg('heart_rate');
        $heartrates = $heartrates ? round($heartrates, 1) : null;
        return response()->json($heartrates,200);
    }

    public function store(Request $request){
        $heartRate = $request->user()
        ->heartRates()
        ->create(
            $request->validate([
                'heart_rate'  => ['required', 'integer', 'min:1'],
                'recorded_at' => ['nullable', 'date'],
                'context'     => ['nullable', 'in:resting,exercise,waking,other'],
            ])
        );

        $heartRate->refresh();

        StreakService::update($request->user(), $heartRate->recorded_at);

        broadcast(new UploadCreated($request->user()->id, ["type"=>"heart_rate","data" => $heartRate]));

        return response()->json($heartRate, 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $request->user()->heartRates()->findOrFail($id);
        $record->update($request->validate([
            'heart_rate'  => ['required', 'integer', 'min:1', 'max:300'],
            'recorded_at' => ['nullable', 'date'],
            'context'     => ['nullable', 'in:resting,exercise,waking,other'],
        ]));
        return response()->json($record);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->heartRates()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
