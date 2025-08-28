<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Jenssegers\Agent\Agent; // Impor library yang baru diinstal

class LoginHistory extends Model
{
    use HasFactory;

    /**
     * Tentukan nama tabel jika tidak mengikuti konvensi Laravel.
     */
    protected $table = 'login_histories';

    /**
     * Relasi ke model User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor untuk mengurai user_agent string.
     * Atribut ini akan otomatis ditambahkan saat model diubah menjadi JSON.
     */
    protected function parsedAgent(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $agent = new Agent();
                $agent->setUserAgent($attributes['user_agent']);

                return [
                    'browser' => $agent->browser() . ' ' . $agent->version($agent->browser()),
                    'platform' => $agent->platform() . ' ' . $agent->version($agent->platform()),
                    'device' => $agent->device(),
                    'is_desktop' => $agent->isDesktop(),
                    'is_mobile' => $agent->isMobile(),
                    'is_tablet' => $agent->isTablet(),
                ];
            }
        );
    }

    /**
     * Menambahkan accessor ke output JSON model.
     */
    protected $appends = ['parsed_agent'];
}