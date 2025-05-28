<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Perfil extends BaseModel
{
    use HasFactory;

    protected $fillable = [
       'nome_perfil'
    ];
}
