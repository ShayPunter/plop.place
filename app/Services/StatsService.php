<?php

namespace App\Services;

use App\Models\User;
use App\Models\AnonymousSession;
use App\Models\PixelHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsService
{
    public const CACHE_TTL_GLOBAL = 60;      // 1 minute
    public const CACHE_TTL_LEADERBOARD = 300; // 5 minutes
    public const CACHE_TTL_COLORS = 300;      // 5 minutes
    public const CACHE_TTL_ACTIVITY = 30;     // 30 seconds

    /**
     * Get global statistics
     */
    public function getGlobalStats(): array
    {
        return Cache::remember('stats:global', self::CACHE_TTL_GLOBAL, function () {
            return [
                'total_pixels' => PixelHistory::count(),
                'total_users' => User::count(),
                'total_anonymous' => AnonymousSession::where('pixels_placed', '>', 0)->count(),
                'pixels_last_hour' => PixelHistory::where('created_at', '>=', now()->subHour())->count(),
                'pixels_last_day' => PixelHistory::where('created_at', '>=', now()->subDay())->count(),
                'active_users_today' => $this->getActiveUsersToday(),
                'canvas_coverage' => $this->getCanvasCoverage(),
            ];
        });
    }

    /**
     * Get top users leaderboard
     */
    public function getTopUsers(int $limit = 20): array
    {
        return Cache::remember("stats:leaderboard:{$limit}", self::CACHE_TTL_LEADERBOARD, function () use ($limit) {
            // Get registered users
            $users = User::select('id', 'name', 'pixels_placed')
                ->where('pixels_placed', '>', 0)
                ->orderByDesc('pixels_placed')
                ->limit($limit)
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'pixels_placed' => $u->pixels_placed,
                    'type' => 'user',
                ]);

            // Get anonymous sessions
            $anonymous = AnonymousSession::select('id', 'pixels_placed')
                ->where('pixels_placed', '>', 0)
                ->orderByDesc('pixels_placed')
                ->limit($limit)
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'name' => 'Anonymous #' . $s->id,
                    'pixels_placed' => $s->pixels_placed,
                    'type' => 'anonymous',
                ]);

            // Merge and sort
            return $users->merge($anonymous)
                ->sortByDesc('pixels_placed')
                ->take($limit)
                ->values()
                ->toArray();
        });
    }

    /**
     * Get color usage distribution
     */
    public function getColorDistribution(): array
    {
        return Cache::remember('stats:colors', self::CACHE_TTL_COLORS, function () {
            $colors = PixelHistory::select('color', DB::raw('COUNT(*) as count'))
                ->groupBy('color')
                ->orderByDesc('count')
                ->get()
                ->pluck('count', 'color')
                ->toArray();

            // Fill in missing colors with 0
            $distribution = [];
            for ($i = 0; $i < 16; $i++) {
                $distribution[$i] = $colors[$i] ?? 0;
            }

            return $distribution;
        });
    }

    /**
     * Get recent activity timeline
     */
    public function getActivityTimeline(int $hours = 24, int $buckets = 24): array
    {
        return Cache::remember("stats:activity:{$hours}:{$buckets}", self::CACHE_TTL_ACTIVITY, function () use ($hours, $buckets) {
            $bucketSize = ($hours * 3600) / $buckets;
            $startTime = now()->subHours($hours);

            $activity = PixelHistory::select(
                    DB::raw("FLOOR(UNIX_TIMESTAMP(created_at) / {$bucketSize}) as bucket"),
                    DB::raw('COUNT(*) as count')
                )
                ->where('created_at', '>=', $startTime)
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            $timeline = [];
            $currentBucket = floor($startTime->timestamp / $bucketSize);

            for ($i = 0; $i < $buckets; $i++) {
                $bucket = $currentBucket + $i;
                $found = $activity->firstWhere('bucket', $bucket);
                $timeline[] = [
                    'time' => (int) ($bucket * $bucketSize),
                    'count' => $found ? (int) $found->count : 0,
                ];
            }

            return $timeline;
        });
    }

    /**
     * Count unique active users/sessions today
     */
    private function getActiveUsersToday(): int
    {
        $users = PixelHistory::where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $sessions = PixelHistory::where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('anonymous_session_id')
            ->distinct('anonymous_session_id')
            ->count('anonymous_session_id');

        return $users + $sessions;
    }

    /**
     * Calculate what percentage of canvas has been touched
     */
    private function getCanvasCoverage(): float
    {
        $uniquePixels = DB::table('pixel_history')
            ->selectRaw('COUNT(DISTINCT CONCAT(x, "-", y)) as unique_count')
            ->value('unique_count');

        $totalPixels = CanvasService::CANVAS_WIDTH * CanvasService::CANVAS_HEIGHT;

        return round(($uniquePixels / $totalPixels) * 100, 2);
    }

    /**
     * Clear all stats caches
     */
    public function clearCache(): void
    {
        Cache::forget('stats:global');
        Cache::forget('stats:leaderboard:20');
        Cache::forget('stats:colors');
        Cache::forget('stats:activity:24:24');
    }
}
