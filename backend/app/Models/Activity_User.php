<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity_User extends Model
{
    protected $table = 'activity__users';
    protected $fillable = ['begin','end','user_id','activity_id'];

    public function activity(){
        return $this->belongsTo(Activity::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
}
