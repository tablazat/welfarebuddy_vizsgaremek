<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\UploadCreated;
use App\Services\StreakService;

class StepController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date', '1970-01-01');
        $endDate = $request->query('end_date', '9999-12-31');

        $steps = $request->user()->steps()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();

        return response()->json($steps, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'steps' => ['required', 'integer', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:overwrite,add'], // overwrite = felülírás, add = hozzáadás
        ]);

        $date = $validated['recorded_at'] ?? now()->toDateString();
        $mode = $validated['mode'] ?? 'overwrite';

        $step = DB::transaction(function () use ($request, $validated, $date, $mode) {
            $existing = $request->user()->steps()->lockForUpdate()->where('recorded_at', $date)->first();

            if ($existing) {
                if ($mode === 'add') {
                    $existing->steps += $validated['steps'];
                } else {
                    $existing->steps = $validated['steps'];
                }
                $existing->save();
                return $existing;
            }

            return $request->user()->steps()->create([
                'recorded_at' => $date,
                'steps' => $validated['steps'],
            ]);
        });

        StreakService::update($request->user(), $date);

        broadcast(new UploadCreated($request->user()->id, ['type' => 'steps', 'data' => $step]));

        return response()->json($step, 201);
    }

    public function today(Request $request)
    {
        $step = $request->user()->steps()
            ->where('recorded_at', now()->toDateString())
            ->first();

        return response()->json($step, 200);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->steps()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
