<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funcionalidades extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'descricao', 'status'];

    public function planos()
    {
        return $this->belongsToMany(Planos::class);
    }
}
