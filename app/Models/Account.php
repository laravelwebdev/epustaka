<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Account extends Model
{
    protected $fillable = [
        'email',
    ];

    protected static function booted(): void
    {
        static::creating(function (Account $account) {
            $account->user_id = Auth::user()->id;
        });
    }
}
