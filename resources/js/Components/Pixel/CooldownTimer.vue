<template>
    <div
        class="bg-gray-900/95 backdrop-blur-sm rounded-lg px-4 py-2 shadow-xl"
        :class="{ 'opacity-50': canPlace }"
    >
        <div v-if="canPlace" class="text-green-400 font-medium">
            Ready to place!
        </div>
        <div v-else class="flex items-center gap-3">
            <div class="relative w-8 h-8">
                <svg class="w-8 h-8 transform -rotate-90" viewBox="0 0 32 32">
                    <circle
                        class="text-gray-700"
                        stroke-width="3"
                        stroke="currentColor"
                        fill="transparent"
                        r="14"
                        cx="16"
                        cy="16"
                    />
                    <circle
                        class="text-blue-500 transition-all duration-1000"
                        stroke-width="3"
                        stroke="currentColor"
                        fill="transparent"
                        r="14"
                        cx="16"
                        cy="16"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="strokeDashoffset"
                    />
                </svg>
            </div>
            <div class="text-white">
                <div class="text-sm text-gray-400">Next pixel in</div>
                <div class="font-mono text-lg font-bold">
                    {{ formattedTime }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

interface Props {
    cooldownEnd: number | null;
    cooldownDuration: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    complete: [];
}>();

const remaining = ref(0);
const canPlace = computed(() => remaining.value <= 0);

const circumference = 2 * Math.PI * 14;

const progress = computed(() => {
    if (!props.cooldownEnd || props.cooldownDuration <= 0) return 1;
    return 1 - remaining.value / props.cooldownDuration;
});

const strokeDashoffset = computed(() => {
    return circumference * (1 - progress.value);
});

const formattedTime = computed(() => {
    const secs = Math.max(0, Math.ceil(remaining.value));
    const mins = Math.floor(secs / 60);
    const remainingSecs = secs % 60;

    if (mins > 0) {
        return `${mins}:${remainingSecs.toString().padStart(2, '0')}`;
    }
    return `${remainingSecs}s`;
});

let intervalId: number | null = null;

function updateRemaining() {
    if (!props.cooldownEnd) {
        remaining.value = 0;
        return;
    }

    const now = Math.floor(Date.now() / 1000);
    remaining.value = Math.max(0, props.cooldownEnd - now);

    if (remaining.value <= 0 && intervalId) {
        clearInterval(intervalId);
        intervalId = null;
        emit('complete');
    }
}

watch(
    () => props.cooldownEnd,
    () => {
        updateRemaining();

        if (props.cooldownEnd && !intervalId) {
            intervalId = window.setInterval(updateRemaining, 100);
        }
    },
    { immediate: true }
);

onMounted(() => {
    if (props.cooldownEnd) {
        intervalId = window.setInterval(updateRemaining, 100);
    }
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>
