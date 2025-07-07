<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para gerenciamento de fotos
 * 
 * Representa as fotos armazenadas no sistema, associadas a pastas específicas.
 * Cada foto possui caminho de armazenamento e data em que foi tirada.
 */
class Photos extends Model
{
    protected $fillable = [
        'pasta_id',
        'path',
        'taken_at'
    ];

    /**
     * Relacionamento: uma foto pertence a uma pasta
     * 
     * Cada foto está obrigatoriamente associada a uma pasta específica
     * onde está armazenada e organizada.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pastas()
    {
        return $this->belongsTo(Pastas::class);
    }
}
