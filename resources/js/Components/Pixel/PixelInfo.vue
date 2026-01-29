<template>
    <div
        v-if="x !== null && y !== null"
        class="bg-gray-900/95 backdrop-blur-sm rounded-lg px-4 py-2 shadow-xl"
    >
        <div class="flex items-center gap-3">
            <div
                class="w-8 h-8 rounded border border-gray-600"
                :style="{ backgroundColor: colorHex }"
            />
            <div class="text-white text-sm">
                <div class="font-mono">
                    ({{ x }}, {{ y }})
                </div>
                <div class="text-gray-400 text-xs">
                    {{ colorHex }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    x: number | null;
    y: number | null;
    color: number;
    palette: Record<number, string>;
}

const props = defineProps<Props>();

const colorHex = computed(() => {
    return props.palette[props.color] || '#FFFFFF';
});
</script>
