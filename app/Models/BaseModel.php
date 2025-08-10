<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modelo base para todos os outros models do sistema
 * 
 * Fornece funcionalidades comuns como soft deletes, UUID automático,
 * e métodos utilitários para busca por UUID.
 */
class BaseModel extends Model
{
    use SoftDeletes, HasFactory;

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Configurações de inicialização do modelo
     * 
     * Automaticamente gera um UUID para cada novo registro criado.
     * Executado quando um novo modelo é instanciado.
     */
    public static function boot()
    {
        parent::boot();

        static::creating(
            function ($model) {
                $model->uuid = Str::uuid();
            }
        );
    }

    /**
     * Busca um registro pelo UUID
     * 
     * @param string $uuid - UUID único do registro
     * @return self - Instância do modelo encontrado
     * @throws ModelNotFoundException - Se o registro não for encontrado
     */
    public static function findByUuid(string $uuid): self
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }
}
