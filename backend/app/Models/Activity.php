<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['type','name_hu','name_en','name_de'];

    public function activity_users(){
        return $this->hasMany(Activity_User::class);
    }
}
