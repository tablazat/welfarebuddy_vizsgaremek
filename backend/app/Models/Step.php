<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Step extends Model
{
    protected $fillable = ['steps', 'recorded_at', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
