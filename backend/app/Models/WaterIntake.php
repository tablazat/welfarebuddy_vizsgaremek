<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterIntake extends Model
{
    protected $fillable = ['amount_ml', 'user_id', 'recorded_at'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
