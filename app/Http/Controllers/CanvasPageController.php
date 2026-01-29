<?php

namespace App\Http\Controllers;

use App\Models\AnonymousSession;
use App\Services\CanvasService;
use App\Services\CooldownService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CanvasPageController extends Controller
{
    public function __construct(
        private CanvasService $canvasService,
        private CooldownService $cooldownService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $sessionToken = null;
        $canPlace = true;
        $cooldownEnd = null;

        // Handle anonymous session
        if (!$user) {
            $token = $request->cookie('session_token');
            if ($token) {
                $session = AnonymousSession::findByToken($token);
                if ($session) {
                    $sessionToken = $token;
                    $canPlace = $this->cooldownService->canPlacePixel($session);
                    $cooldownEnd = $this->cooldownService->getCooldownEndTime($session);
                }
            }
        } else {
            $canPlace = $this->cooldownService->canPlacePixel($user);
            $cooldownEnd = $this->cooldownService->getCooldownEndTime($user);
        }

        return Inertia::render('Canvas', [
            'canvasConfig' => [
                'width' => CanvasService::CANVAS_WIDTH,
                'height' => CanvasService::CANVAS_HEIGHT,
                'palette' => CanvasService::getPalette(),
                'cooldownSeconds' => CooldownService::COOLDOWN_SECONDS,
            ],
            'initialState' => [
                'canPlace' => $canPlace,
                'cooldownEnd' => $cooldownEnd,
                'sessionToken' => $sessionToken,
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'pixels_placed' => $user->pixels_placed,
                ] : null,
            ],
        ]);
    }
}
