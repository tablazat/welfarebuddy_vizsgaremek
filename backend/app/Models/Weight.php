<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Weight extends Model
{
    protected $fillable = ['weight', 'user_id', 'recorded_at'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
