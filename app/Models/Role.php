<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    /**
     * Mendapatkan semua user dengan peran ini.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

}
