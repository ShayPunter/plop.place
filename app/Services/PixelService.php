<?php

namespace App\Services;

use App\Events\PixelPlaced;
use App\Models\AnonymousSession;
use App\Models\PixelHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PixelService
{
    public const RECENT_PIXELS_KEY = 'pixels:recent';
    public const RECENT_PIXELS_LIMIT = 1000;

    public function __construct(
        private CanvasService $canvasService,
        private CooldownService $cooldownService,
    ) {}

    public function placePixel(
        int $x,
        int $y,
        int $color,
        User|AnonymousSession $entity
    ): array {
        // Check cooldown
        if (!$this->cooldownService->canPlacePixel($entity)) {
            $remaining = $this->cooldownService->getRemainingCooldown($entity);
            return [
                'success' => false,
                'error' => 'cooldown',
                'remaining' => $remaining,
                'message' => "Please wait {$remaining} seconds before placing another pixel",
            ];
        }

        // Validate coordinates and color
        try {
            $this->validatePlacement($x, $y, $color);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => 'invalid',
                'message' => $e->getMessage(),
            ];
        }

        // Get previous color and set new color
        $previousColor = $this->canvasService->setPixel($x, $y, $color);

        // Record in history
        $history = $this->recordHistory($x, $y, $color, $previousColor, $entity);

        // Update user/session stats
        $this->updateStats($entity);

        // Start cooldown
        $this->cooldownService->startCooldown($entity);

        // Add to recent pixels for catch-up
        $this->addToRecentPixels($x, $y, $color);

        // Broadcast the event
        broadcast(new PixelPlaced($x, $y, $color, $history->id))->toOthers();

        return [
            'success' => true,
            'x' => $x,
            'y' => $y,
            'color' => $color,
            'previous_color' => $previousColor,
            'cooldown_end' => time() + CooldownService::COOLDOWN_SECONDS,
        ];
    }

    public function getRecentPixels(int $since = null, int $limit = 100): array
    {
        if ($since === null) {
            $since = time() - 60; // Last minute by default
        }

        $pixels = Redis::zrangebyscore(
            self::RECENT_PIXELS_KEY,
            $since,
            '+inf',
            ['limit' => [0, $limit]]
        );

        return array_map(function ($pixel) {
            $data = json_decode($pixel, true);
            return $data;
        }, $pixels ?: []);
    }

    private function validatePlacement(int $x, int $y, int $color): void
    {
        if ($x < 0 || $x >= CanvasService::CANVAS_WIDTH) {
            throw new \InvalidArgumentException(
                "X coordinate must be between 0 and " . (CanvasService::CANVAS_WIDTH - 1)
            );
        }

        if ($y < 0 || $y >= CanvasService::CANVAS_HEIGHT) {
            throw new \InvalidArgumentException(
                "Y coordinate must be between 0 and " . (CanvasService::CANVAS_HEIGHT - 1)
            );
        }

        $maxColor = count(CanvasService::PALETTE) - 1;
        if ($color < 0 || $color > $maxColor) {
            throw new \InvalidArgumentException(
                "Color must be between 0 and $maxColor"
            );
        }
    }

    private function recordHistory(
        int $x,
        int $y,
        int $color,
        int $previousColor,
        User|AnonymousSession $entity
    ): PixelHistory {
        $data = [
            'x' => $x,
            'y' => $y,
            'color' => $color,
            'previous_color' => $previousColor,
        ];

        if ($entity instanceof User) {
            $data['user_id'] = $entity->id;
        } else {
            $data['anonymous_session_id'] = $entity->id;
        }

        return PixelHistory::create($data);
    }

    private function updateStats(User|AnonymousSession $entity): void
    {
        $entity->pixels_placed++;
        $entity->last_pixel_at = now();
        $entity->save();
    }

    private function addToRecentPixels(int $x, int $y, int $color): void
    {
        $timestamp = time();
        $data = json_encode([
            'x' => $x,
            'y' => $y,
            'color' => $color,
            'timestamp' => $timestamp,
        ]);

        Redis::zadd(self::RECENT_PIXELS_KEY, $timestamp, $data);

        // Trim old entries
        $cutoff = $timestamp - 300; // Keep last 5 minutes
        Redis::zremrangebyscore(self::RECENT_PIXELS_KEY, '-inf', $cutoff);
    }
}
