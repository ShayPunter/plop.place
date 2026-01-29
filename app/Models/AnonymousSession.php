<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AnonymousSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'ip_address',
        'pixels_placed',
        'last_pixel_at',
    ];

    protected $casts = [
        'last_pixel_at' => 'datetime',
    ];

    public static function createWithToken(string $ipAddress = null): self
    {
        return self::create([
            'token' => Str::random(64),
            'ip_address' => $ipAddress,
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }

    public function pixelHistory()
    {
        return $this->hasMany(PixelHistory::class);
    }
}
