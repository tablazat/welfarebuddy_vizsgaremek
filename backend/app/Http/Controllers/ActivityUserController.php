<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\UploadCreated;
use App\Services\StreakService;

class ActivityUserController extends Controller
{
    public function index(Request $request){
        $exercises = $request->user()->activity_users()->orderBy('begin')->get();
        return response()->json($exercises,200,options:JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "begin"       => "required|date_format:Y-m-d H:i:s",
            "end"         => "required|date_format:Y-m-d H:i:s|after_or_equal:begin",
            "activity_id" => "required|exists:activities,id"
        ]);

        $exercise = $request->user()->activity_users()->create($validated);

        $user = $request->user();
        StreakService::update($user, $exercise->begin);

        broadcast(new UploadCreated($user->id, ["type"=>"exercise","data"=>$exercise]));

        return response()->json($exercise, 201, options: JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, int $id)
    {
        $record = $request->user()->activity_users()->findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
