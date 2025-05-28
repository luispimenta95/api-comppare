<?php

namespace App\Models;



class Photos extends BaseModel
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
