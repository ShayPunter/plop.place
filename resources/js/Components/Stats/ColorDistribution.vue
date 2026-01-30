<template>
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
        <div class="space-y-3">
            <div
                v-for="[colorIndex, count] in sortedColors"
                :key="colorIndex"
                class="flex items-center gap-3"
            >
                <!-- Color swatch -->
                <div
                    class="w-6 h-6 rounded border border-gray-600 flex-shrink-0"
                    :style="{ backgroundColor: palette[colorIndex] }"
                />

                <!-- Bar -->
                <div class="flex-1 h-6 bg-gray-700 rounded overflow-hidden">
                    <div
                        class="h-full transition-all duration-500"
                        :style="{
                            width: `${getPercentage(count)}%`,
                            backgroundColor: palette[colorIndex],
                        }"
                    />
                </div>

                <!-- Count -->
                <div class="w-20 text-right text-sm font-mono text-gray-400">
                    {{ formatNumber(count) }}
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-4 pt-4 border-t border-gray-700 text-sm text-gray-500">
            Total: {{ formatNumber(totalPixels) }} pixels placed
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    distribution: Record<number, number>;
    palette: Record<number, string>;
}>();

const totalPixels = computed(() => {
    return Object.values(props.distribution).reduce((sum, count) => sum + count, 0);
});

const sortedColors = computed(() => {
    return Object.entries(props.distribution)
        .map(([key, value]) => [parseInt(key), value] as [number, number])
        .sort(([, a], [, b]) => b - a);
});

function getPercentage(count: number): number {
    if (totalPixels.value === 0) return 0;
    return (count / totalPixels.value) * 100;
}

function formatNumber(num: number): string {
    return num.toLocaleString();
}
</script>
