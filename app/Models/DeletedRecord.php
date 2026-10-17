<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedRecord extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }
}
