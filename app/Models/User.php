<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id', // Ditambahkan
        'division_id',
    ];

    protected $hidden = [ // Nama variabel $hidden ditambahkan
        'password',
        'remember_token',
    ];

    protected $casts = [ // Nama variabel $casts ditambahkan
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class); // $this ditambahkan
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'uploader_id'); // $this ditambahkan
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class); // $this ditambahkan
    }
}