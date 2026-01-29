<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PixelHistory extends Model
{
    use HasFactory;

    protected $table = 'pixel_history';

    protected $fillable = [
        'x',
        'y',
        'color',
        'previous_color',
        'user_id',
        'anonymous_session_id',
    ];

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
        'color' => 'integer',
        'previous_color' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function anonymousSession()
    {
        return $this->belongsTo(AnonymousSession::class);
    }
}
