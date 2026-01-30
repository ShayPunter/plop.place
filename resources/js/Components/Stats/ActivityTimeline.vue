<template>
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
        <!-- Bar chart using CSS -->
        <div class="flex items-end gap-1 h-32">
            <div
                v-for="(item, index) in data"
                :key="index"
                class="flex-1 bg-blue-500/80 hover:bg-blue-500 transition-colors rounded-t cursor-pointer relative group"
                :style="{
                    height: `${getBarHeight(item.count)}%`,
                    minHeight: item.count > 0 ? '4px' : '0',
                }"
            >
                <!-- Tooltip -->
                <div
                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10"
                >
                    {{ formatTime(item.time) }}: {{ item.count }} pixels
                </div>
            </div>
        </div>

        <!-- Time labels -->
        <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span>24h ago</span>
            <span>12h ago</span>
            <span>Now</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: Array<{
        time: number;
        count: number;
    }>;
}>();

const maxCount = computed(() => {
    return Math.max(...props.data.map((d) => d.count), 1);
});

function getBarHeight(count: number): number {
    return (count / maxCount.value) * 100;
}

function formatTime(timestamp: number): string {
    return new Date(timestamp * 1000).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>
