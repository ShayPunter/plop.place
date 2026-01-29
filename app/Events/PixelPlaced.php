<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PixelPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $x,
        public int $y,
        public int $color,
        public int $historyId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('canvas'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pixel.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'color' => $this->color,
            'timestamp' => now()->timestamp,
        ];
    }
}
