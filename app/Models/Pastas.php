<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para gerenciamento de pastas de usuários
 * 
 * Representa as pastas criadas pelos usuários para organizar suas fotos.
 * Cada pasta pode conter múltiplas fotos e estar associada a múltiplos usuários.
 */
class Pastas extends Model
{
    protected $fillable = ['nome', 'idUsuario', 'caminho', 'idPastaPai'];

    /**
     * Relacionamento: uma pasta pode ter múltiplos usuários
     * 
     * Relacionamento many-to-many através da tabela pasta_usuario.
     * Permite que uma pasta seja compartilhada entre vários usuários.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function usuario()
    {
        return $this->belongsToMany(Usuarios::class, 'pasta_usuario', 'pasta_id', 'usuario_id');
    }

    /**
     * Relacionamento: uma pasta pode ter um convite
     * 
     * Permite enviar convites para compartilhar a pasta com outros usuários.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function convite()
    {
        return $this->hasOne(Convite::class);
    }

    /**
     * Relacionamento: uma pasta pode ter múltiplas fotos
     * 
     * Todas as fotos armazenadas dentro desta pasta específica.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function photos()
    {
        return $this->hasMany(Photos::class, 'pasta_id');
    }

    /**
     * Relacionamento: uma pasta tem muitas tags com valores associados
     * 
     * Permite categorizar e organizar pastas com tags personalizadas.
     * Cada tag pode ter um valor específico associado à pasta.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'folder_tag_values')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Relacionamento: uma pasta pode ter subpastas
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subpastas()
    {
        return $this->hasMany(Pastas::class, 'idPastaPai');
    }

    /**
     * Relacionamento: uma pasta pode ter uma pasta pai
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pastaPai()
    {
        return $this->belongsTo(Pastas::class, 'idPastaPai');
    }
}
