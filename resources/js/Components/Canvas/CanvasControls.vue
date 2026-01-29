<template>
    <div class="absolute top-4 right-4 flex flex-col gap-2">
        <button
            @click="$emit('zoomIn')"
            class="w-10 h-10 bg-gray-800/90 hover:bg-gray-700 text-white rounded-lg flex items-center justify-center text-xl font-bold shadow-lg transition-colors"
            title="Zoom in"
        >
            +
        </button>
        <button
            @click="$emit('zoomOut')"
            class="w-10 h-10 bg-gray-800/90 hover:bg-gray-700 text-white rounded-lg flex items-center justify-center text-xl font-bold shadow-lg transition-colors"
            title="Zoom out"
        >
            -
        </button>
        <button
            @click="$emit('resetView')"
            class="w-10 h-10 bg-gray-800/90 hover:bg-gray-700 text-white rounded-lg flex items-center justify-center shadow-lg transition-colors"
            title="Reset view"
        >
            <svg
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"
                />
            </svg>
        </button>
    </div>
</template>

<script setup lang="ts">
defineEmits<{
    zoomIn: [];
    zoomOut: [];
    resetView: [];
}>();
</script>
