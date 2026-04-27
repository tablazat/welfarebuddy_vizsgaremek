<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request){
        $activities = Activity::all();
        return response()->json($activities,200,options:JSON_UNESCAPED_UNICODE);
    }
}
