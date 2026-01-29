<?php

namespace App\Console\Commands;

use App\Services\CanvasService;
use Illuminate\Console\Command;

class InitializeCanvas extends Command
{
    protected $signature = 'canvas:init {--reset : Reset the canvas to blank}';

    protected $description = 'Initialize or reset the canvas';

    public function handle(CanvasService $canvasService): int
    {
        if ($this->option('reset')) {
            if (!$this->confirm('This will reset the entire canvas. Are you sure?')) {
                $this->info('Cancelled.');
                return 0;
            }

            $canvasService->resetCanvas();
            $this->info('Canvas has been reset to blank.');
        } else {
            $canvasService->initializeCanvas();
            $this->info('Canvas initialized.');
        }

        $this->info(sprintf(
            'Canvas size: %dx%d pixels (%d KB)',
            CanvasService::CANVAS_WIDTH,
            CanvasService::CANVAS_HEIGHT,
            (CanvasService::CANVAS_WIDTH * CanvasService::CANVAS_HEIGHT * CanvasService::BITS_PER_PIXEL / 8) / 1024
        ));

        return 0;
    }
}
