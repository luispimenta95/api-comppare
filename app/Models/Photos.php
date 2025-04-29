<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photos extends Model
{

    protected $fillable = [
        'pasta_id',
        'path',
        'taken_at'
    ];

    /**
     * Relacionamento: uma foto pertence a uma pasta.
     */
    public function pastas()
    {
        return $this->belongsTo(Pastas::class);
    }

    /**
 */

}
