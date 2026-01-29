<template>
    <div
        ref="containerRef"
        class="relative w-full h-full overflow-hidden bg-gray-800 cursor-crosshair select-none"
        @mousedown="handleMouseDown"
        @mousemove="handleMouseMove"
        @mouseup="handleMouseUp"
        @mouseleave="handleMouseUp"
        @wheel="handleWheel"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
    >
        <!-- Offscreen canvas for pixel data -->
        <canvas
            ref="dataCanvasRef"
            :width="config.width"
            :height="config.height"
            class="hidden"
        />

        <!-- Visible canvas for rendering -->
        <canvas
            ref="displayCanvasRef"
            :width="displayWidth"
            :height="displayHeight"
            class="absolute"
            :style="canvasStyle"
        />

        <!-- Grid overlay (shown at high zoom) -->
        <div
            v-if="showGrid"
            class="absolute pointer-events-none"
            :style="gridStyle"
        />

        <!-- Hover indicator -->
        <div
            v-if="hoveredPixel && !isDragging"
            class="absolute pointer-events-none border-2 border-white shadow-lg"
            :style="hoverStyle"
        />

        <!-- Loading overlay -->
        <div
            v-if="loading"
            class="absolute inset-0 flex items-center justify-center bg-gray-900/80"
        >
            <div class="text-white text-lg">Loading canvas...</div>
        </div>

        <!-- Coordinates display -->
        <div
            class="absolute bottom-4 left-4 bg-gray-900/80 text-white px-3 py-1 rounded text-sm font-mono"
        >
            <span v-if="hoveredPixel">
                ({{ hoveredPixel.x }}, {{ hoveredPixel.y }})
            </span>
            <span v-else>Move cursor over canvas</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useViewport } from '@/Composables/useViewport';
import { CanvasRenderer } from '@/Services/CanvasRenderer';

interface Props {
    config: {
        width: number;
        height: number;
        palette: Record<number, string>;
        cooldownSeconds: number;
    };
    canvasData: Uint8Array | null;
    loading: boolean;
    selectedColor: number;
    canPlace: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    pixelClick: [x: number, y: number];
    pixelHover: [x: number, y: number, color: number];
}>();

const containerRef = ref<HTMLDivElement | null>(null);
const dataCanvasRef = ref<HTMLCanvasElement | null>(null);
const displayCanvasRef = ref<HTMLCanvasElement | null>(null);

const displayWidth = ref(window.innerWidth);
const displayHeight = ref(window.innerHeight);

const hoveredPixel = ref<{ x: number; y: number } | null>(null);
const renderer = ref<CanvasRenderer | null>(null);
const lastPinchDistance = ref(0);

const {
    viewport,
    isDragging,
    screenToCanvas,
    startDrag,
    drag,
    endDrag,
    handleWheel: onWheel,
    zoom,
} = useViewport({
    canvasWidth: props.config.width,
    canvasHeight: props.config.height,
    minZoom: 0.5,
    maxZoom: 50,
    initialZoom: 4,
});

const showGrid = computed(() => viewport.zoom >= 8);

const canvasStyle = computed(() => {
    const scale = viewport.zoom;
    const offsetX = displayWidth.value / 2 - viewport.x * scale;
    const offsetY = displayHeight.value / 2 - viewport.y * scale;

    return {
        transform: `translate(${offsetX}px, ${offsetY}px) scale(${scale})`,
        transformOrigin: '0 0',
        imageRendering: 'pixelated' as const,
    };
});

const gridStyle = computed(() => {
    const scale = viewport.zoom;
    const offsetX = displayWidth.value / 2 - viewport.x * scale;
    const offsetY = displayHeight.value / 2 - viewport.y * scale;

    return {
        width: `${props.config.width * scale}px`,
        height: `${props.config.height * scale}px`,
        transform: `translate(${offsetX}px, ${offsetY}px)`,
        backgroundImage: `
            linear-gradient(to right, rgba(255,255,255,0.1) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(255,255,255,0.1) 1px, transparent 1px)
        `,
        backgroundSize: `${scale}px ${scale}px`,
    };
});

const hoverStyle = computed(() => {
    if (!hoveredPixel.value || !containerRef.value) return {};

    const rect = containerRef.value.getBoundingClientRect();
    const scale = viewport.zoom;
    const offsetX = displayWidth.value / 2 - viewport.x * scale;
    const offsetY = displayHeight.value / 2 - viewport.y * scale;

    return {
        left: `${offsetX + hoveredPixel.value.x * scale}px`,
        top: `${offsetY + hoveredPixel.value.y * scale}px`,
        width: `${scale}px`,
        height: `${scale}px`,
    };
});

function handleMouseDown(event: MouseEvent) {
    if (event.button === 0) {
        startDrag(event.clientX, event.clientY);
    }
}

function handleMouseMove(event: MouseEvent) {
    if (!containerRef.value) return;

    const rect = containerRef.value.getBoundingClientRect();

    if (isDragging.value) {
        drag(event.clientX, event.clientY);
    }

    const { x, y } = screenToCanvas(event.clientX, event.clientY, rect);

    if (x >= 0 && x < props.config.width && y >= 0 && y < props.config.height) {
        hoveredPixel.value = { x, y };

        // Get pixel color
        if (renderer.value && props.canvasData) {
            const index = y * props.config.width + x;
            const byteIndex = Math.floor(index / 2);
            const nibbleIndex = index % 2;
            const byte = props.canvasData[byteIndex] || 0;
            const color = nibbleIndex === 0 ? (byte >> 4) & 0x0f : byte & 0x0f;
            emit('pixelHover', x, y, color);
        }
    } else {
        hoveredPixel.value = null;
    }
}

function handleMouseUp(event: MouseEvent) {
    if (!isDragging.value && event.button === 0 && hoveredPixel.value && props.canPlace) {
        emit('pixelClick', hoveredPixel.value.x, hoveredPixel.value.y);
    }
    endDrag();
}

function handleWheel(event: WheelEvent) {
    if (!containerRef.value) return;
    event.preventDefault();
    onWheel(event, containerRef.value.getBoundingClientRect());
    requestAnimationFrame(render);
}

// Touch handling
function handleTouchStart(event: TouchEvent) {
    if (event.touches.length === 1) {
        const touch = event.touches[0];
        startDrag(touch.clientX, touch.clientY);
    } else if (event.touches.length === 2) {
        const dx = event.touches[0].clientX - event.touches[1].clientX;
        const dy = event.touches[0].clientY - event.touches[1].clientY;
        lastPinchDistance.value = Math.sqrt(dx * dx + dy * dy);
    }
}

function handleTouchMove(event: TouchEvent) {
    event.preventDefault();

    if (event.touches.length === 1 && isDragging.value) {
        const touch = event.touches[0];
        drag(touch.clientX, touch.clientY);
    } else if (event.touches.length === 2) {
        const dx = event.touches[0].clientX - event.touches[1].clientX;
        const dy = event.touches[0].clientY - event.touches[1].clientY;
        const distance = Math.sqrt(dx * dx + dy * dy);

        if (lastPinchDistance.value > 0) {
            const delta = (distance - lastPinchDistance.value) / lastPinchDistance.value;
            zoom(delta * 0.5);
        }

        lastPinchDistance.value = distance;
    }
}

function handleTouchEnd(event: TouchEvent) {
    if (event.touches.length === 0) {
        if (!isDragging.value && hoveredPixel.value && props.canPlace) {
            emit('pixelClick', hoveredPixel.value.x, hoveredPixel.value.y);
        }
        endDrag();
        lastPinchDistance.value = 0;
    }
}

function render() {
    if (!renderer.value || !props.canvasData) return;
    renderer.value.renderFull(props.canvasData);
}

function updatePixel(x: number, y: number, color: number) {
    if (!renderer.value) return;
    renderer.value.renderPixel(x, y, color);
}

function handleResize() {
    displayWidth.value = window.innerWidth;
    displayHeight.value = window.innerHeight;
}

// Initialize renderer
onMounted(async () => {
    await nextTick();

    if (dataCanvasRef.value) {
        renderer.value = new CanvasRenderer(dataCanvasRef.value, {
            canvasWidth: props.config.width,
            canvasHeight: props.config.height,
            palette: props.config.palette,
        });
    }

    window.addEventListener('resize', handleResize);
    handleResize();
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

// Watch for canvas data changes
watch(
    () => props.canvasData,
    (newData) => {
        if (newData && renderer.value) {
            render();

            // Copy to display canvas
            if (displayCanvasRef.value && dataCanvasRef.value) {
                const ctx = displayCanvasRef.value.getContext('2d');
                if (ctx) {
                    displayCanvasRef.value.width = props.config.width;
                    displayCanvasRef.value.height = props.config.height;
                    ctx.imageSmoothingEnabled = false;
                    ctx.drawImage(dataCanvasRef.value, 0, 0);
                }
            }
        }
    },
    { immediate: true }
);

// Expose methods for parent
defineExpose({
    updatePixel,
    render,
});
</script>
