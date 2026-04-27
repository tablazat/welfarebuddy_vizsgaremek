<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Models\CalorieIntake;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'email',
        'password',
        "level_of_access",
        "max_days",
        "height_cm",
        "step_goal_daily",
        "water_goal_ml",
        "locale",
        'profile_picture',
        'terms_accepted_at',
    ];

    protected $appends = ['profile_picture_url'];

    public function getProfilePictureUrlAttribute(): ?string
    {
        if (!$this->profile_picture) return null;
        return url('storage/' . $this->profile_picture);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function heartRates(){
        return $this->hasMany(HeartRate::class);
    }

    public function bloodPressures(){
        return $this->hasMany(BloodPressure::class);
    }

    public function weights(){
        return $this->hasMany(Weight::class);
    }

    public function streak(){
        return $this->hasOne(Streak::class);
    }
    public function activity_users(){
        return $this->hasMany(Activity_User::class);
    }

    public function steps(){
        return $this->hasMany(Step::class);
    }

    public function deviceTokens(){
        return $this->hasMany(DeviceToken::class);
    }

    public function calorieIntakes(){
        return $this->hasMany(CalorieIntake::class);
    }

    public function waterIntakes(){
        return $this->hasMany(WaterIntake::class);
    }

    public function sleepRecords(){
        return $this->hasMany(SleepRecord::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(
            (new VerifyEmailNotification)->locale($this->locale)
        );
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(
            (new ResetPasswordNotification($token))->locale($this->locale)
        );
    }
}
