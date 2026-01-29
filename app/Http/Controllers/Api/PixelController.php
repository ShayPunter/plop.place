<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnonymousSession;
use App\Services\CooldownService;
use App\Services\PixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PixelController extends Controller
{
    public function __construct(
        private PixelService $pixelService,
        private CooldownService $cooldownService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'x' => 'required|integer|min:0|max:999',
            'y' => 'required|integer|min:0|max:999',
            'color' => 'required|integer|min:0|max:15',
        ]);

        $entity = $this->getPlacementEntity($request);

        if (!$entity) {
            return response()->json([
                'success' => false,
                'error' => 'no_session',
                'message' => 'No valid session found',
            ], 401);
        }

        $result = $this->pixelService->placePixel(
            $validated['x'],
            $validated['y'],
            $validated['color'],
            $entity
        );

        $statusCode = $result['success'] ? 200 : 429;
        if (isset($result['error']) && $result['error'] === 'invalid') {
            $statusCode = 400;
        }

        return response()->json($result, $statusCode);
    }

    public function cooldown(Request $request): JsonResponse
    {
        $entity = $this->getPlacementEntity($request);

        if (!$entity) {
            return response()->json([
                'can_place' => false,
                'error' => 'no_session',
            ], 401);
        }

        $canPlace = $this->cooldownService->canPlacePixel($entity);
        $remaining = $this->cooldownService->getRemainingCooldown($entity);
        $endTime = $this->cooldownService->getCooldownEndTime($entity);

        return response()->json([
            'can_place' => $canPlace,
            'remaining' => $remaining,
            'cooldown_end' => $endTime,
            'cooldown_duration' => CooldownService::COOLDOWN_SECONDS,
        ]);
    }

    private function getPlacementEntity(Request $request)
    {
        // First check if user is authenticated
        if ($user = $request->user()) {
            return $user;
        }

        // Check for anonymous session token in header or cookie
        $token = $request->header('X-Session-Token')
            ?? $request->cookie('session_token');

        if ($token) {
            return AnonymousSession::findByToken($token);
        }

        return null;
    }
}
