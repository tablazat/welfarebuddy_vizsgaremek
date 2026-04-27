<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;

class WeightController extends Controller
{
    public function index(Request $request){
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate = $request->query('end_date', '9999-12-31');
        $weights = $request->user()->weights()->whereBetween('recorded_at', [$startDate, $endDate])->orderBy('recorded_at')->get();
        return response()->json($weights,200);
    }

    public function show(Request $request){
        $weight = $request->user()->weights()->orderBy('recorded_at','desc')->first();
        return response()->json($weight,200);
    }

    public function store(Request $request){
        $weight = $request->user()
        ->weights()
        ->create(
            $request->validate([
                'weight'  => ['required', 'numeric', 'min:1'],
                'recorded_at' => ['nullable', 'date'],
            ])
        );

        $weight->refresh();

        StreakService::update($request->user(), $weight->recorded_at);

        broadcast(new UploadCreated($request->user()->id, ["type"=>"weight","data"=>$weight]));

        return response()->json($weight, 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $request->user()->weights()->findOrFail($id);
        $record->update($request->validate([
            'weight'      => ['required', 'numeric', 'min:1', 'max:600'],
            'recorded_at' => ['nullable', 'date'],
        ]));
        return response()->json($record);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->weights()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
