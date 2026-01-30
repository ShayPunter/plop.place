<?php

namespace App\Services;

use App\Models\CanvasSnapshot;
use Illuminate\Support\Facades\Redis;

class CanvasService
{
    public const CANVAS_WIDTH = 1000;
    public const CANVAS_HEIGHT = 1000;
    public const BITS_PER_PIXEL = 4; // 16 colors (0-15)
    public const REDIS_KEY = 'canvas:state';
    public const DEFAULT_COLOR = 15; // White

    // 16-color palette (similar to r/place)
    public const PALETTE = [
        0 => '#000000',  // Black
        1 => '#BE0039',  // Red
        2 => '#FF4500',  // Orange
        3 => '#FFA800',  // Yellow
        4 => '#FFD635',  // Light yellow
        5 => '#00A368',  // Green
        6 => '#00CC78',  // Light green
        7 => '#7EED56',  // Lime
        8 => '#00756F',  // Teal
        9 => '#009EAA',  // Cyan
        10 => '#2450A4', // Blue
        11 => '#3690EA', // Light blue
        12 => '#51E9F4', // Sky blue
        13 => '#493AC1', // Purple
        14 => '#811E9F', // Magenta
        15 => '#FFFFFF', // White
    ];

    public function getPixel(int $x, int $y): int
    {
        $this->validateCoordinates($x, $y);
        $offset = $this->calculateBitOffset($x, $y);

        $result = Redis::command('BITFIELD', [
            self::REDIS_KEY,
            'GET',
            'u' . self::BITS_PER_PIXEL,
            $offset,
        ]);

        return $result[0] ?? self::DEFAULT_COLOR;
    }

    public function setPixel(int $x, int $y, int $color): int
    {
        $this->validateCoordinates($x, $y);
        $this->validateColor($color);

        $offset = $this->calculateBitOffset($x, $y);

        // GET old value and SET new value atomically
        $result = Redis::command('BITFIELD', [
            self::REDIS_KEY,
            'GET',
            'u' . self::BITS_PER_PIXEL,
            $offset,
            'SET',
            'u' . self::BITS_PER_PIXEL,
            $offset,
            $color,
        ]);

        return $result[0] ?? self::DEFAULT_COLOR;
    }

    public function getCanvas(): string
    {
        $data = Redis::get(self::REDIS_KEY);

        if ($data === null || $data === false) {
            // Return empty canvas filled with default color
            return $this->createEmptyCanvas();
        }

        return $data;
    }

    public function getCanvasAsArray(): array
    {
        $binaryData = $this->getCanvas();
        $canvas = [];

        for ($y = 0; $y < self::CANVAS_HEIGHT; $y++) {
            for ($x = 0; $x < self::CANVAS_WIDTH; $x++) {
                $index = $y * self::CANVAS_WIDTH + $x;
                $byteIndex = intdiv($index, 2);
                $nibbleIndex = $index % 2;

                if ($byteIndex < strlen($binaryData)) {
                    $byte = ord($binaryData[$byteIndex]);
                    $color = $nibbleIndex === 0
                        ? ($byte >> 4) & 0x0F
                        : $byte & 0x0F;
                } else {
                    $color = self::DEFAULT_COLOR;
                }

                $canvas[$y][$x] = $color;
            }
        }

        return $canvas;
    }

    public function initializeCanvas(): void
    {
        if (!Redis::exists(self::REDIS_KEY)) {
            Redis::set(self::REDIS_KEY, $this->createEmptyCanvas());
        }
    }

    public function resetCanvas(): void
    {
        Redis::set(self::REDIS_KEY, $this->createEmptyCanvas());
    }

    public function saveSnapshot(): CanvasSnapshot
    {
        $data = $this->getCanvas();
        return CanvasSnapshot::createFromData($data);
    }

    public function restoreFromSnapshot(CanvasSnapshot $snapshot): void
    {
        $data = $snapshot->getDecompressedData();
        Redis::set(self::REDIS_KEY, $data);
    }

    public function restoreFromLatestSnapshot(): bool
    {
        $snapshot = CanvasSnapshot::latest()->first();

        if ($snapshot) {
            $this->restoreFromSnapshot($snapshot);
            return true;
        }

        return false;
    }

    private function calculateBitOffset(int $x, int $y): int
    {
        $pixelIndex = $y * self::CANVAS_WIDTH + $x;
        return $pixelIndex * self::BITS_PER_PIXEL;
    }

    private function validateCoordinates(int $x, int $y): void
    {
        if ($x < 0 || $x >= self::CANVAS_WIDTH || $y < 0 || $y >= self::CANVAS_HEIGHT) {
            throw new \InvalidArgumentException(
                "Coordinates ($x, $y) are out of bounds. Canvas size is " .
                self::CANVAS_WIDTH . "x" . self::CANVAS_HEIGHT
            );
        }
    }

    private function validateColor(int $color): void
    {
        $maxColor = (1 << self::BITS_PER_PIXEL) - 1;
        if ($color < 0 || $color > $maxColor) {
            throw new \InvalidArgumentException(
                "Color $color is invalid. Must be between 0 and $maxColor"
            );
        }
    }

    private function createEmptyCanvas(): string
    {
        // Each pixel is 4 bits, so 2 pixels per byte
        // 1000 * 1000 = 1,000,000 pixels = 500,000 bytes
        $totalPixels = self::CANVAS_WIDTH * self::CANVAS_HEIGHT;
        $totalBytes = intdiv($totalPixels + 1, 2);

        // Fill with default color (white = 15 = 0xF)
        // Two white pixels per byte = 0xFF
        $defaultByte = (self::DEFAULT_COLOR << 4) | self::DEFAULT_COLOR;

        return str_repeat(chr($defaultByte), $totalBytes);
    }

    public static function getPalette(): array
    {
        return self::PALETTE;
    }

    public static function getColorHex(int $colorIndex): string
    {
        return self::PALETTE[$colorIndex] ?? self::PALETTE[self::DEFAULT_COLOR];
    }
}
