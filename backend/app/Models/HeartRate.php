<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeartRate extends Model
{
    protected $fillable = ['heart_rate','user_id','recorded_at','context'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
