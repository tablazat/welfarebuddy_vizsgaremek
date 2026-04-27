<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;

class BloodPressureController extends Controller
{
    public function index(Request $request){
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate = $request->query('end_date', '9999-12-31');
        $bloodpressures = $request->user()->bloodPressures()->whereBetween('recorded_at', [$startDate, $endDate])->orderBy('recorded_at')->get();
        return response()->json($bloodpressures,200);
    }

    public function average(Request $request){
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate = $request->query('end_date', '9999-12-31');
        $base = $request->user()->bloodPressures()->whereBetween('recorded_at', [$startDate, $endDate]);
        $systolic = (clone $base)->avg('systolic');
        $diastolic = (clone $base)->avg('diastolic');
        $morningSys = (clone $base)->where('period', 'morning')->avg('systolic');
        $morningDia = (clone $base)->where('period', 'morning')->avg('diastolic');
        $eveningSys = (clone $base)->where('period', 'evening')->avg('systolic');
        $eveningDia = (clone $base)->where('period', 'evening')->avg('diastolic');
        $round = fn($v) => $v ? round($v, 1) : null;
        return response()->json([
            "systolic"    => $round($systolic),
            "diastolic"   => $round($diastolic),
            "morning"     => ["systolic" => $round($morningSys), "diastolic" => $round($morningDia)],
            "evening"     => ["systolic" => $round($eveningSys), "diastolic" => $round($eveningDia)],
        ], 200);
    }

    public function store(Request $request){
        $bloodPressure = $request->user()
        ->bloodPressures()
        ->create(
            $request->validate([
                'systolic'  => ['required', 'integer', 'min:1'],
                'diastolic'  => ['required', 'integer', 'min:1'],
                'recorded_at' => ['nullable', 'date'],
                'period'      => ['nullable', 'in:morning,evening,other'],
            ])
        );

        $bloodPressure->refresh();

        StreakService::update($request->user(), $bloodPressure->recorded_at);

        broadcast(new UploadCreated($request->user()->id, ["data"=>$bloodPressure, "type" =>"blood_pressure"]));

        return response()->json($bloodPressure, 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $request->user()->bloodPressures()->findOrFail($id);
        $record->update($request->validate([
            'systolic'    => ['required', 'integer', 'min:1', 'max:300'],
            'diastolic'   => ['required', 'integer', 'min:1', 'max:200'],
            'recorded_at' => ['nullable', 'date'],
            'period'      => ['nullable', 'in:morning,evening,other'],
        ]));
        return response()->json($record);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->bloodPressures()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
