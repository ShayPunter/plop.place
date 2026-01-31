<template>
    <div class="bg-gray-900/95 backdrop-blur-sm rounded-lg p-2 sm:p-3 shadow-xl">
        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 sm:gap-1">
            <button
                v-for="(hex, index) in palette"
                :key="index"
                @click="$emit('select', Number(index))"
                class="w-10 h-10 sm:w-8 sm:h-8 rounded transition-all hover:scale-110 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900"
                :class="{
                    'ring-2 ring-white ring-offset-2 ring-offset-gray-900 scale-110': selectedColor === Number(index),
                }"
                :style="{ backgroundColor: hex }"
                :title="`Color ${index}: ${hex}`"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
interface Props {
    palette: Record<number, string>;
    selectedColor: number;
}

defineProps<Props>();

defineEmits<{
    select: [colorIndex: number];
}>();
</script>
