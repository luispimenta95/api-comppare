<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Funcionalidades extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'descricao', 'status'];

    public function planos()
    {
        return $this->belongsToMany(Planos::class);
    }
}
