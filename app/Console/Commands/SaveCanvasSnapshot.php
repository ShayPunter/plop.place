<?php

namespace App\Console\Commands;

use App\Services\CanvasService;
use Illuminate\Console\Command;

class SaveCanvasSnapshot extends Command
{
    protected $signature = 'canvas:snapshot';

    protected $description = 'Save a snapshot of the current canvas state';

    public function handle(CanvasService $canvasService): int
    {
        $snapshot = $canvasService->saveSnapshot();

        $this->info(sprintf(
            'Snapshot saved (ID: %d, Size: %d bytes)',
            $snapshot->id,
            strlen($snapshot->data)
        ));

        return 0;
    }
}
