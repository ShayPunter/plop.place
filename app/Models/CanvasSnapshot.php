<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CanvasSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'data',
        'pixel_count',
    ];

    protected $casts = [
        'pixel_count' => 'integer',
    ];

    public static function createFromData(string $binaryData): self
    {
        return self::create([
            'data' => gzcompress($binaryData),
            'pixel_count' => strlen($binaryData),
        ]);
    }

    public function getDecompressedData(): string
    {
        return gzuncompress($this->data);
    }
}
