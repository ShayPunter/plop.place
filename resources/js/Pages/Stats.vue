<template>
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Header -->
        <header class="sticky top-0 z-10 bg-gray-900/95 backdrop-blur border-b border-gray-800">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold">Plop.Place Stats</h1>
                </div>
                <Link
                    href="/"
                    class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                    Back to Canvas
                </Link>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <!-- Global Stats Grid -->
            <section class="mb-12">
                <h2 class="text-xl font-semibold mb-4 text-gray-300">Global Statistics</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <StatCard
                        title="Total Pixels"
                        :value="formatNumber(globalStats.total_pixels)"
                        icon="pixels"
                    />
                    <StatCard
                        title="Registered Users"
                        :value="formatNumber(globalStats.total_users)"
                        icon="users"
                    />
                    <StatCard
                        title="Anonymous Artists"
                        :value="formatNumber(globalStats.total_anonymous)"
                        icon="anonymous"
                    />
                    <StatCard
                        title="Canvas Coverage"
                        :value="`${globalStats.canvas_coverage}%`"
                        icon="coverage"
                    />
                    <StatCard
                        title="Pixels Last Hour"
                        :value="formatNumber(globalStats.pixels_last_hour)"
                        icon="clock"
                    />
                    <StatCard
                        title="Pixels Today"
                        :value="formatNumber(globalStats.pixels_last_day)"
                        icon="calendar"
                    />
                    <StatCard
                        title="Active Today"
                        :value="formatNumber(globalStats.active_users_today)"
                        icon="activity"
                    />
                </div>
            </section>

            <!-- Two column layout for leaderboard and colors -->
            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Leaderboard -->
                <section>
                    <h2 class="text-xl font-semibold mb-4 text-gray-300">Top Contributors</h2>
                    <Leaderboard :users="leaderboard" />
                </section>

                <!-- Color Distribution -->
                <section>
                    <h2 class="text-xl font-semibold mb-4 text-gray-300">Color Usage</h2>
                    <ColorDistribution :distribution="colorDistribution" :palette="palette" />
                </section>
            </div>

            <!-- Activity Timeline -->
            <section v-if="activityTimeline.length" class="mt-12">
                <h2 class="text-xl font-semibold mb-4 text-gray-300">Activity (Last 24 Hours)</h2>
                <ActivityTimeline :data="activityTimeline" />
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-800 mt-12 py-6 text-center text-gray-500 text-sm">
            Stats update every minute
        </footer>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StatCard from '@/Components/Stats/StatCard.vue';
import Leaderboard from '@/Components/Stats/Leaderboard.vue';
import ColorDistribution from '@/Components/Stats/ColorDistribution.vue';
import ActivityTimeline from '@/Components/Stats/ActivityTimeline.vue';

interface Props {
    globalStats: {
        total_pixels: number;
        total_users: number;
        total_anonymous: number;
        pixels_last_hour: number;
        pixels_last_day: number;
        active_users_today: number;
        canvas_coverage: number;
    };
    leaderboard: Array<{
        id: number;
        name: string;
        pixels_placed: number;
        type: 'user' | 'anonymous';
    }>;
    colorDistribution: Record<number, number>;
    activityTimeline: Array<{
        time: number;
        count: number;
    }>;
    palette: Record<number, string>;
}

defineProps<Props>();

function formatNumber(num: number): string {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toLocaleString();
}
</script>
