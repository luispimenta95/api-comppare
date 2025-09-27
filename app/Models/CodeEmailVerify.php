<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class CodeEmailVerify extends Model
{

     protected $fillable = [
        'code',
        'token',
        'resend_available_at',
        'sent_at',
        'user_id',
        'expires_at',
    ];

   public static function generateCode()
    {
        return str_pad(strval(fake()->numberBetween(0, 999999)), 6, '0', STR_PAD_LEFT);
    }

}
