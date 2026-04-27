<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BloodPressureController;
use App\Http\Controllers\HeartRateController;
use App\Http\Controllers\ProfilePictureController;
use App\Http\Controllers\WeightController;
use App\Http\Controllers\StepController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HealthSyncController;
use App\Http\Controllers\CalorieIntakeController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\StreakController;
use App\Http\Controllers\WaterIntakeController;
use App\Http\Controllers\SleepRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Password;

//Route::get('/user', function (Request $request) {return $request->user();})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/heart-rates',[HeartRateController::class,'index']);
    Route::get('/average/heart-rates',[HeartRateController::class,'average']);
    Route::post('/new-heart-rate',[HeartRateController::class,'store']);
    Route::put('/heart-rates/{id}',[HeartRateController::class,'update']);
    Route::delete('/heart-rates/{id}',[HeartRateController::class,'destroy']);
    Route::get('/blood-pressures',[BloodPressureController::class,'index']);
    Route::get('/average/blood-pressures',[BloodPressureController::class,'average']);
    Route::post('/new-blood-pressure',[BloodPressureController::class,'store']);
    Route::put('/blood-pressures/{id}',[BloodPressureController::class,'update']);
    Route::delete('/blood-pressures/{id}',[BloodPressureController::class,'destroy']);
    Route::get('/weights',[WeightController::class,'index']);
    Route::get('/current-weight',[WeightController::class,'show']);
    Route::post('/new-weight',[WeightController::class,'store']);
    Route::put('/weights/{id}',[WeightController::class,'update']);
    Route::delete('/weights/{id}',[WeightController::class,'destroy']);
    Route::get("/activities", [ActivityController::class,"index"]);
    Route::get("/exercises",[ActivityUserController::class,'index']);
    Route::post("/new-exercise",[ActivityUserController::class,'store']);
    Route::delete("/exercises/{id}",[ActivityUserController::class,'destroy']);
    Route::get("/steps",[StepController::class,'index']);
    Route::get("/steps/today",[StepController::class,'today']);
    Route::post("/new-steps",[StepController::class,'store']);
    Route::delete("/steps/{id}",[StepController::class,'destroy']);
    Route::get('/calories', [CalorieIntakeController::class, 'index']);
    Route::post('/new-calorie', [CalorieIntakeController::class, 'store']);
    Route::put('/calories/{id}', [CalorieIntakeController::class, 'update']);
    Route::delete('/calories/{id}', [CalorieIntakeController::class, 'destroy']);
    Route::get('/waters', [WaterIntakeController::class, 'index']);
    Route::get('/waters/today', [WaterIntakeController::class, 'today']);
    Route::post('/new-water', [WaterIntakeController::class, 'store']);
    Route::put('/waters/{id}', [WaterIntakeController::class, 'update']);
    Route::delete('/waters/{id}', [WaterIntakeController::class, 'destroy']);
    Route::get('/sleeps', [SleepRecordController::class, 'index']);
    Route::get('/sleeps/last-night', [SleepRecordController::class, 'lastNight']);
    Route::post('/new-sleep', [SleepRecordController::class, 'store']);
    Route::put('/sleeps/{id}', [SleepRecordController::class, 'update']);
    Route::delete('/sleeps/{id}', [SleepRecordController::class, 'destroy']);
    Route::post('/health-sync', [HealthSyncController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/device-token', [DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
    Route::get('/token-check', function(Request $request){
        $user = $request->user();
        return response()->json(['user'=>$user, 'streak'=>$user->streak],200, options:JSON_UNESCAPED_UNICODE);
    });
    Route::get('/config', function () {
        return response()->json([
            'reverb' => [
                'key'      => env('REVERB_APP_KEY'),
                'host'     => env('REVERB_HOST', 'ws.welfarebuddy.hu'),
                'port'     => (int) env('REVERB_PORT', 443),
                'scheme'   => env('REVERB_SCHEME', 'https'),
                'forceTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
        ]);
    });
    Route::post('/logout',[AuthController::class,'logout']);
    Route::post('/nuke',[AuthController::class,'nuke']);
    Route::delete('/account',[AuthController::class,'deleteAccount']);
    Route::get('/export/json', [ExportController::class, 'json']);
    Route::get('/export/csv',  [ExportController::class, 'csv']);
    Route::get('/streak/status', [StreakController::class, 'status']);
    Route::post('/streak/freeze', [StreakController::class, 'freeze'])->middleware('throttle:5,1');
    Route::post("password-change",[AuthController::class,'passwordchange'])->middleware('throttle:5,1');
    Route::post('/profile-picture',[ProfilePictureController::class,'store']);
    Route::delete('/profile-picture',[ProfilePictureController::class,'destroy']);
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['code' => 'already_verified', 'message' => 'Already verified.']);
        }
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['code' => 'verification_sent', 'message' => 'Verification email sent.']);
    });
    Route::put("change-plan",[AuthController::class,"updateLvl"]);
    Route::post('/locale', function (Request $request) {
        $request->validate(['locale' => 'required|string|in:hu,en,de']);
        $request->user()->update(['locale' => $request->locale]);
        return response()->json(['message' => 'ok']);
    });
    Route::post('/height', function (Request $request) {
        $request->validate(['height_cm' => 'required|integer|min:50|max:300']);
        $request->user()->update(['height_cm' => $request->height_cm]);
        return response()->json(['user' => $request->user()->fresh()]);
    });
    Route::put('/me/goals', function (Request $request) {
        $data = $request->validate([
            'step_goal_daily' => 'nullable|integer|min:1000|max:100000',
            'water_goal_ml'   => 'nullable|integer|min:500|max:10000',
        ]);
        $request->user()->update(array_filter($data, fn($v) => $v !== null));
        return response()->json(['user' => $request->user()->fresh()]);
    });

    Route::put('/me/profile', function (Request $request) {
        $data = $request->validate([
            'name'         => 'nullable|string|min:2|max:100',
            'display_name' => 'nullable|string|min:1|max:64',
        ]);
        $request->user()->update(array_filter($data, fn($v) => $v !== null));
        return response()->json(['user' => $request->user()->fresh()]);
    });

    Route::get('/me/progress', [ProgressController::class, 'show']);

    // Admin route-ok – csak admin felhasználóknak
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'user']);
        Route::put('/users/{user}/level', [AdminController::class, 'updateLevel']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::get('/users/{user}/export/json', [ExportController::class, 'adminJson']);
        Route::get('/users/{user}/export/csv',  [ExportController::class, 'adminCsv']);
    });
});


Route::get('/email/verify/{id}/{hash}', function (Request $request) {
    $user = User::findOrFail($request->id);

    if (! hash_equals((string) $request->hash, sha1($user->email))) {
        return response()->json(['code' => 'invalid_link', 'message' => 'Invalid verification link.'], 403);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['code' => 'already_verified', 'message' => 'Already verified.']);
    }

    $user->markEmailAsVerified();

    return response()->json(['code' => 'verified', 'message' => 'Email verified successfully.']);
})->middleware('signed')->name('verification.verify');



Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    // Ha küld locale-t a frontend, frissítjük a user-nél
    if ($request->locale) {
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update(['locale' => $request->locale]);
        }
    }

    $status = Password::sendResetLink($request->only('email'));

    return $status === Password::RESET_LINK_SENT
        ? response()->json(['message' => 'Reset link sent.'])
        : response()->json(['message' => 'Unable to send reset link.'], 400);
})->middleware('throttle:5,1');

Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token'    => 'required',
        'email'    => 'required|email',
        'password' => 'required|min:8|confirmed', // needs password_confirmation field
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function (User $user, string $password) {
            $user->password = $password; // hashed cast handles bcrypt automatically
            $user->save();
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        $user = User::where('email', $request['email'])->first();
        if ($user) {
            $user->tokens()->delete();
            $user->save();
        }
        return response()->json(['message' => 'Password reset successfully.']);
    }

    return response()->json(['message' => 'Invalid token or email.'], 400);
})->middleware('throttle:5,1');