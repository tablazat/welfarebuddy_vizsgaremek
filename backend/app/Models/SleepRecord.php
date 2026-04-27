<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepRecord extends Model
{
    protected $fillable = ['hours', 'quality', 'user_id', 'recorded_at'];

    protected $casts = [
        'hours'   => 'decimal:2',
        'quality' => 'integer',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
