<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalorieIntake extends Model
{
    protected $fillable = ['data', 'user_id', 'recorded_at'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
