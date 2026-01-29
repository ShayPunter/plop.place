<template>
    <div
        ref="containerRef"
        class="relative w-full h-full overflow-hidden bg-gray-800 select-none"
        :class="isDragging ? 'cursor-grabbing' : 'cursor-crosshair'"
        @mousedown="handleMouseDown"
        @mousemove="handleMouseMove"
        @mouseup="handleMouseUp"
        @mouseleave="handleMouseLeave"
        @wheel.prevent="handleWheel"
        @touchstart.prevent="handleTouchStart"
        @touchmove.prevent="handleTouchMove"
        @touchend="handleTouchEnd"
        @contextmenu.prevent
    >
        <!-- Main rendering canvas -->
        <canvas
            ref="canvasRef"
            class="absolute top-0 left-0"
            :style="{ imageRendering: 'pixelated' }"
        />

        <!-- Loading overlay -->
        <div
            v-if="loading"
            class="absolute inset-0 flex items-center justify-center bg-gray-900/80 z-10"
        >
            <div class="text-white text-lg">Loading canvas...</div>
        </div>

        <!-- Coordinates display -->
        <div
            class="absolute bottom-4 left-4 bg-gray-900/90 text-white px-3 py-2 rounded text-sm font-mono z-10"
        >
            <div v-if="hoveredPixel">
                <span class="text-gray-400">Position:</span> ({{ hoveredPixel.x }}, {{ hoveredPixel.y }})
                <span class="ml-2 text-gray-400">Zoom:</span> {{ Math.round(viewport.zoom * 100) / 100 }}x
            </div>
            <div v-else class="text-gray-400">Move cursor over canvas</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';

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

// Refs
const containerRef = ref<HTMLDivElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const ctx = ref<CanvasRenderingContext2D | null>(null);

// Source image data (1000x1000 pixels stored as ImageData)
const sourceImageData = ref<ImageData | null>(null);

// Viewport state
const viewport = reactive({
    x: props.config.width / 2,
    y: props.config.height / 2,
    zoom: 4,
});

// Interaction state
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const viewportStart = ref({ x: 0, y: 0 });
const lastPinchDistance = ref(0);
const hoveredPixel = ref<{ x: number; y: number } | null>(null);

// Animation
let animationFrameId: number | null = null;
let needsRender = true;

// Palette as RGB array for fast lookup
const paletteRgb: [number, number, number][] = [];

// Constants
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 50;
const GRID_ZOOM_THRESHOLD = 6;

// Initialize palette
function initPalette() {
    paletteRgb.length = 0;
    for (let i = 0; i < 16; i++) {
        const hex = props.config.palette[i] || '#FFFFFF';
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        paletteRgb.push([r, g, b]);
    }
}

// Convert screen coordinates to canvas pixel coordinates
function screenToCanvas(screenX: number, screenY: number): { x: number; y: number } {
    if (!containerRef.value) return { x: -1, y: -1 };

    const rect = containerRef.value.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    const canvasX = viewport.x + (screenX - rect.left - centerX) / viewport.zoom;
    const canvasY = viewport.y + (screenY - rect.top - centerY) / viewport.zoom;

    return {
        x: Math.floor(canvasX),
        y: Math.floor(canvasY),
    };
}

// Build source ImageData from binary canvas data
function buildSourceImage() {
    if (!props.canvasData) return;

    const { width, height } = props.config;
    sourceImageData.value = new ImageData(width, height);
    const pixels = sourceImageData.value.data;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const index = y * width + x;
            const byteIndex = Math.floor(index / 2);
            const nibbleIndex = index % 2;

            let colorIndex = 15;
            if (byteIndex < props.canvasData.length) {
                const byte = props.canvasData[byteIndex];
                colorIndex = nibbleIndex === 0 ? (byte >> 4) & 0x0f : byte & 0x0f;
            }

            const rgb = paletteRgb[colorIndex] || [255, 255, 255];
            const pixelOffset = index * 4;
            pixels[pixelOffset] = rgb[0];
            pixels[pixelOffset + 1] = rgb[1];
            pixels[pixelOffset + 2] = rgb[2];
            pixels[pixelOffset + 3] = 255;
        }
    }

    needsRender = true;
}

// Update a single pixel in the source image
function updateSourcePixel(x: number, y: number, colorIndex: number) {
    if (!sourceImageData.value) return;

    const { width } = props.config;
    const index = y * width + x;
    const pixelOffset = index * 4;
    const rgb = paletteRgb[colorIndex] || [255, 255, 255];

    sourceImageData.value.data[pixelOffset] = rgb[0];
    sourceImageData.value.data[pixelOffset + 1] = rgb[1];
    sourceImageData.value.data[pixelOffset + 2] = rgb[2];

    needsRender = true;
}

// Main render function - only renders visible portion
function render() {
    if (!ctx.value || !canvasRef.value || !sourceImageData.value) return;

    const canvas = canvasRef.value;
    const c = ctx.value;
    const { width: canvasWidth, height: canvasHeight } = props.config;

    // Clear canvas
    c.fillStyle = '#1f2937';
    c.fillRect(0, 0, canvas.width, canvas.height);

    // Calculate visible area in canvas coordinates
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const zoom = viewport.zoom;

    // Calculate the top-left corner in canvas pixel coordinates
    const viewLeft = viewport.x - centerX / zoom;
    const viewTop = viewport.y - centerY / zoom;
    const viewRight = viewport.x + centerX / zoom;
    const viewBottom = viewport.y + centerY / zoom;

    // Clamp to canvas bounds
    const srcX = Math.max(0, Math.floor(viewLeft));
    const srcY = Math.max(0, Math.floor(viewTop));
    const srcX2 = Math.min(canvasWidth, Math.ceil(viewRight) + 1);
    const srcY2 = Math.min(canvasHeight, Math.ceil(viewBottom) + 1);
    const srcW = srcX2 - srcX;
    const srcH = srcY2 - srcY;

    if (srcW <= 0 || srcH <= 0) return;

    // Create a temporary canvas for the visible portion
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = srcW;
    tempCanvas.height = srcH;
    const tempCtx = tempCanvas.getContext('2d')!;

    // Extract visible portion from source
    const tempImageData = tempCtx.createImageData(srcW, srcH);
    const srcData = sourceImageData.value.data;
    const dstData = tempImageData.data;

    for (let y = 0; y < srcH; y++) {
        for (let x = 0; x < srcW; x++) {
            const srcIndex = ((srcY + y) * canvasWidth + (srcX + x)) * 4;
            const dstIndex = (y * srcW + x) * 4;
            dstData[dstIndex] = srcData[srcIndex];
            dstData[dstIndex + 1] = srcData[srcIndex + 1];
            dstData[dstIndex + 2] = srcData[srcIndex + 2];
            dstData[dstIndex + 3] = srcData[srcIndex + 3];
        }
    }

    tempCtx.putImageData(tempImageData, 0, 0);

    // Draw to main canvas with scaling
    c.imageSmoothingEnabled = false;
    const destX = centerX + (srcX - viewport.x) * zoom;
    const destY = centerY + (srcY - viewport.y) * zoom;
    const destW = srcW * zoom;
    const destH = srcH * zoom;

    c.drawImage(tempCanvas, destX, destY, destW, destH);

    // Draw grid if zoomed in enough
    if (zoom >= GRID_ZOOM_THRESHOLD) {
        drawGrid(c, centerX, centerY, zoom, srcX, srcY, srcX2, srcY2);
    }

    // Draw hover highlight
    if (hoveredPixel.value && !isDragging.value) {
        const hx = hoveredPixel.value.x;
        const hy = hoveredPixel.value.y;
        if (hx >= 0 && hx < canvasWidth && hy >= 0 && hy < canvasHeight) {
            const px = centerX + (hx - viewport.x) * zoom;
            const py = centerY + (hy - viewport.y) * zoom;

            c.strokeStyle = 'rgba(255, 255, 255, 0.9)';
            c.lineWidth = 2;
            c.strokeRect(px, py, zoom, zoom);

            // Inner highlight with selected color
            c.fillStyle = props.config.palette[props.selectedColor] || '#FFFFFF';
            c.globalAlpha = 0.3;
            c.fillRect(px, py, zoom, zoom);
            c.globalAlpha = 1;
        }
    }

    // Draw canvas border
    const borderX = centerX + (0 - viewport.x) * zoom;
    const borderY = centerY + (0 - viewport.y) * zoom;
    c.strokeStyle = 'rgba(255, 255, 255, 0.3)';
    c.lineWidth = 1;
    c.strokeRect(borderX, borderY, canvasWidth * zoom, canvasHeight * zoom);
}

// Draw grid lines
function drawGrid(
    c: CanvasRenderingContext2D,
    centerX: number,
    centerY: number,
    zoom: number,
    srcX: number,
    srcY: number,
    srcX2: number,
    srcY2: number
) {
    c.strokeStyle = 'rgba(255, 255, 255, 0.15)';
    c.lineWidth = 1;

    c.beginPath();

    // Vertical lines
    for (let x = srcX; x <= srcX2; x++) {
        const px = centerX + (x - viewport.x) * zoom;
        c.moveTo(Math.round(px) + 0.5, centerY + (srcY - viewport.y) * zoom);
        c.lineTo(Math.round(px) + 0.5, centerY + (srcY2 - viewport.y) * zoom);
    }

    // Horizontal lines
    for (let y = srcY; y <= srcY2; y++) {
        const py = centerY + (y - viewport.y) * zoom;
        c.moveTo(centerX + (srcX - viewport.x) * zoom, Math.round(py) + 0.5);
        c.lineTo(centerX + (srcX2 - viewport.x) * zoom, Math.round(py) + 0.5);
    }

    c.stroke();
}

// Animation loop
function renderLoop() {
    if (needsRender) {
        render();
        needsRender = false;
    }
    animationFrameId = requestAnimationFrame(renderLoop);
}

// Request a render on next frame
function requestRender() {
    needsRender = true;
}

// Mouse handlers
function handleMouseDown(event: MouseEvent) {
    if (event.button === 0) {
        isDragging.value = true;
        dragStart.value = { x: event.clientX, y: event.clientY };
        viewportStart.value = { x: viewport.x, y: viewport.y };
    }
}

function handleMouseMove(event: MouseEvent) {
    if (isDragging.value) {
        const dx = event.clientX - dragStart.value.x;
        const dy = event.clientY - dragStart.value.y;
        viewport.x = viewportStart.value.x - dx / viewport.zoom;
        viewport.y = viewportStart.value.y - dy / viewport.zoom;
        clampViewport();
        requestRender();
    }

    // Update hovered pixel
    const { x, y } = screenToCanvas(event.clientX, event.clientY);
    if (x >= 0 && x < props.config.width && y >= 0 && y < props.config.height) {
        hoveredPixel.value = { x, y };

        // Get pixel color
        if (props.canvasData) {
            const index = y * props.config.width + x;
            const byteIndex = Math.floor(index / 2);
            const nibbleIndex = index % 2;
            const byte = props.canvasData[byteIndex] || 0;
            const color = nibbleIndex === 0 ? (byte >> 4) & 0x0f : byte & 0x0f;
            emit('pixelHover', x, y, color);
        }
        requestRender();
    } else {
        if (hoveredPixel.value) {
            hoveredPixel.value = null;
            requestRender();
        }
    }
}

function handleMouseUp(event: MouseEvent) {
    if (isDragging.value) {
        const dx = Math.abs(event.clientX - dragStart.value.x);
        const dy = Math.abs(event.clientY - dragStart.value.y);

        // Only trigger click if we didn't drag
        if (dx < 5 && dy < 5 && hoveredPixel.value && props.canPlace) {
            emit('pixelClick', hoveredPixel.value.x, hoveredPixel.value.y);
        }
    }
    isDragging.value = false;
}

function handleMouseLeave() {
    isDragging.value = false;
    hoveredPixel.value = null;
    requestRender();
}

function handleWheel(event: WheelEvent) {
    const { x: canvasX, y: canvasY } = screenToCanvas(event.clientX, event.clientY);

    const delta = event.deltaY > 0 ? 0.85 : 1.18;
    const oldZoom = viewport.zoom;
    const newZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, viewport.zoom * delta));

    // Zoom towards cursor
    const factor = 1 - newZoom / oldZoom;
    viewport.x += (canvasX - viewport.x) * factor;
    viewport.y += (canvasY - viewport.y) * factor;
    viewport.zoom = newZoom;

    clampViewport();
    requestRender();
}

// Touch handlers
function handleTouchStart(event: TouchEvent) {
    if (event.touches.length === 1) {
        const touch = event.touches[0];
        isDragging.value = true;
        dragStart.value = { x: touch.clientX, y: touch.clientY };
        viewportStart.value = { x: viewport.x, y: viewport.y };

        const { x, y } = screenToCanvas(touch.clientX, touch.clientY);
        if (x >= 0 && x < props.config.width && y >= 0 && y < props.config.height) {
            hoveredPixel.value = { x, y };
        }
    } else if (event.touches.length === 2) {
        isDragging.value = false;
        const dx = event.touches[0].clientX - event.touches[1].clientX;
        const dy = event.touches[0].clientY - event.touches[1].clientY;
        lastPinchDistance.value = Math.sqrt(dx * dx + dy * dy);
    }
}

function handleTouchMove(event: TouchEvent) {
    if (event.touches.length === 1 && isDragging.value) {
        const touch = event.touches[0];
        const dx = touch.clientX - dragStart.value.x;
        const dy = touch.clientY - dragStart.value.y;
        viewport.x = viewportStart.value.x - dx / viewport.zoom;
        viewport.y = viewportStart.value.y - dy / viewport.zoom;
        clampViewport();
        requestRender();
    } else if (event.touches.length === 2) {
        const dx = event.touches[0].clientX - event.touches[1].clientX;
        const dy = event.touches[0].clientY - event.touches[1].clientY;
        const distance = Math.sqrt(dx * dx + dy * dy);

        if (lastPinchDistance.value > 0) {
            const scale = distance / lastPinchDistance.value;
            viewport.zoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, viewport.zoom * scale));
            clampViewport();
            requestRender();
        }

        lastPinchDistance.value = distance;
    }
}

function handleTouchEnd(event: TouchEvent) {
    if (event.touches.length === 0) {
        if (isDragging.value && hoveredPixel.value && props.canPlace) {
            const dx = Math.abs(dragStart.value.x - viewportStart.value.x);
            const dy = Math.abs(dragStart.value.y - viewportStart.value.y);
            if (dx < 10 && dy < 10) {
                emit('pixelClick', hoveredPixel.value.x, hoveredPixel.value.y);
            }
        }
        isDragging.value = false;
        lastPinchDistance.value = 0;
    }
}

// Clamp viewport to reasonable bounds
function clampViewport() {
    const padding = 200;
    viewport.x = Math.max(-padding, Math.min(props.config.width + padding, viewport.x));
    viewport.y = Math.max(-padding, Math.min(props.config.height + padding, viewport.y));
}

// Handle resize
function handleResize() {
    if (!canvasRef.value || !containerRef.value) return;

    const rect = containerRef.value.getBoundingClientRect();
    canvasRef.value.width = rect.width;
    canvasRef.value.height = rect.height;
    requestRender();
}

// Update pixel (called from parent)
function updatePixel(x: number, y: number, color: number) {
    updateSourcePixel(x, y, color);

    // Also update the raw data
    if (props.canvasData) {
        const index = y * props.config.width + x;
        const byteIndex = Math.floor(index / 2);
        const nibbleIndex = index % 2;

        if (nibbleIndex === 0) {
            props.canvasData[byteIndex] = (color << 4) | (props.canvasData[byteIndex] & 0x0f);
        } else {
            props.canvasData[byteIndex] = (props.canvasData[byteIndex] & 0xf0) | color;
        }
    }
}

// Initialize
onMounted(async () => {
    await nextTick();

    initPalette();

    if (canvasRef.value && containerRef.value) {
        const rect = containerRef.value.getBoundingClientRect();
        canvasRef.value.width = rect.width;
        canvasRef.value.height = rect.height;
        ctx.value = canvasRef.value.getContext('2d', { alpha: false });
    }

    window.addEventListener('resize', handleResize);

    // Start render loop
    renderLoop();
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }
});

// Watch for data changes
watch(
    () => props.canvasData,
    () => {
        if (props.canvasData) {
            buildSourceImage();
        }
    },
    { immediate: true }
);

// Expose methods
defineExpose({
    updatePixel,
    requestRender,
});
</script>
