<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
//use App\Models\User;

class Streak extends Model
{
    protected $fillable = ["user_id", "days", "last_day", "last_freeze_at"];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
