<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BaseModel extends Model
{
    use SoftDeletes, HasFactory;

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(
            function ($model) {
                $model->uuid = Str::uuid();
            }
        );
    }

    public static function findByUuid(string $uuid): self
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }
}
