<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CanvasService;
use App\Services\PixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CanvasController extends Controller
{
    public function __construct(
        private CanvasService $canvasService,
        private PixelService $pixelService,
    ) {}

    public function index(): Response
    {
        $canvasData = $this->canvasService->getCanvas();

        return response($canvasData, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Length', strlen($canvasData))
            ->header('Cache-Control', 'no-cache');
    }

    public function info(): JsonResponse
    {
        return response()->json([
            'width' => CanvasService::CANVAS_WIDTH,
            'height' => CanvasService::CANVAS_HEIGHT,
            'palette' => CanvasService::getPalette(),
            'cooldown_seconds' => \App\Services\CooldownService::COOLDOWN_SECONDS,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $since = $request->input('since');
        $limit = min($request->input('limit', 100), 500);

        $pixels = $this->pixelService->getRecentPixels($since, $limit);

        return response()->json([
            'pixels' => $pixels,
            'timestamp' => time(),
        ]);
    }
}
