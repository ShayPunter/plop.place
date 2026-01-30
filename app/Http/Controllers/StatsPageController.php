<?php

namespace App\Http\Controllers;

use App\Services\CanvasService;
use App\Services\StatsService;
use Inertia\Inertia;
use Inertia\Response;

class StatsPageController extends Controller
{
    public function __construct(
        private StatsService $statsService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Stats', [
            'globalStats' => $this->statsService->getGlobalStats(),
            'leaderboard' => $this->statsService->getTopUsers(20),
            'colorDistribution' => $this->statsService->getColorDistribution(),
            'activityTimeline' => $this->statsService->getActivityTimeline(24, 24),
            'palette' => CanvasService::getPalette(),
        ]);
    }
}
