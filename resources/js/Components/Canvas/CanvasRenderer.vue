<template>
    <div
        ref="containerRef"
        class="relative w-full h-full overflow-hidden bg-gray-800 select-none"
        :class="(isDragging || isPanning) ? 'cursor-grabbing' : 'cursor-crosshair'"
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
import { ref, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue';

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
    selectedPixel?: { x: number; y: number } | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    pixelClick: [x: number, y: number];
    pixelHover: [x: number, y: number, color: number];
    pixelTap: [x: number, y: number];
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
const isPanning = ref(false); // Right-click panning
const dragButton = ref(0); // Which button started the drag
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
const GRID_ZOOM_THRESHOLD = 4; // Show grid earlier

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

    // Draw canvas border
    const borderX = centerX + (0 - viewport.x) * zoom;
    const borderY = centerY + (0 - viewport.y) * zoom;
    c.strokeStyle = 'rgba(255, 255, 255, 0.5)';
    c.lineWidth = 2;
    c.strokeRect(borderX, borderY, canvasWidth * zoom, canvasHeight * zoom);

    // Draw hover highlight with high contrast
    // Use selectedPixel prop (for mobile) or hoveredPixel (for desktop)
    const pixelToHighlight = props.selectedPixel || (hoveredPixel.value && !isDragging.value ? hoveredPixel.value : null);
    if (pixelToHighlight) {
        drawCursor(c, centerX, centerY, zoom, canvasWidth, canvasHeight, pixelToHighlight);
    }
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
    // Draw dark lines first (will show on light backgrounds)
    c.strokeStyle = 'rgba(0, 0, 0, 0.3)';
    c.lineWidth = 1;

    c.beginPath();

    // Vertical lines
    for (let x = srcX; x <= srcX2; x++) {
        const px = Math.round(centerX + (x - viewport.x) * zoom);
        c.moveTo(px + 0.5, centerY + (srcY - viewport.y) * zoom);
        c.lineTo(px + 0.5, centerY + (srcY2 - viewport.y) * zoom);
    }

    // Horizontal lines
    for (let y = srcY; y <= srcY2; y++) {
        const py = Math.round(centerY + (y - viewport.y) * zoom);
        c.moveTo(centerX + (srcX - viewport.x) * zoom, py + 0.5);
        c.lineTo(centerX + (srcX2 - viewport.x) * zoom, py + 0.5);
    }

    c.stroke();

    // Draw lighter lines offset by 1px (will show on dark backgrounds)
    c.strokeStyle = 'rgba(255, 255, 255, 0.15)';
    c.beginPath();

    for (let x = srcX; x <= srcX2; x++) {
        const px = Math.round(centerX + (x - viewport.x) * zoom);
        c.moveTo(px + 1.5, centerY + (srcY - viewport.y) * zoom);
        c.lineTo(px + 1.5, centerY + (srcY2 - viewport.y) * zoom);
    }

    for (let y = srcY; y <= srcY2; y++) {
        const py = Math.round(centerY + (y - viewport.y) * zoom);
        c.moveTo(centerX + (srcX - viewport.x) * zoom, py + 1.5);
        c.lineTo(centerX + (srcX2 - viewport.x) * zoom, py + 1.5);
    }

    c.stroke();
}

// Draw cursor with high contrast (works on any background)
function drawCursor(
    c: CanvasRenderingContext2D,
    centerX: number,
    centerY: number,
    zoom: number,
    canvasWidth: number,
    canvasHeight: number,
    pixel: { x: number; y: number }
) {
    const hx = pixel.x;
    const hy = pixel.y;

    if (hx < 0 || hx >= canvasWidth || hy < 0 || hy >= canvasHeight) return;

    const px = centerX + (hx - viewport.x) * zoom;
    const py = centerY + (hy - viewport.y) * zoom;
    const size = zoom;

    // Draw preview of selected color with transparency
    const selectedColorHex = props.config.palette[props.selectedColor] || '#FFFFFF';
    c.fillStyle = selectedColorHex;
    c.globalAlpha = 0.5;
    c.fillRect(px, py, size, size);
    c.globalAlpha = 1;

    // Outer dark stroke (visible on light backgrounds)
    c.strokeStyle = '#000000';
    c.lineWidth = 3;
    c.strokeRect(px, py, size, size);

    // Inner white stroke (visible on dark backgrounds)
    c.strokeStyle = '#FFFFFF';
    c.lineWidth = 1;
    c.strokeRect(px, py, size, size);

    // Draw corner markers for extra visibility
    const cornerSize = Math.min(6, size / 4);
    c.fillStyle = '#FFFFFF';

    // Top-left corner
    c.fillRect(px - 1, py - 1, cornerSize, 2);
    c.fillRect(px - 1, py - 1, 2, cornerSize);

    // Top-right corner
    c.fillRect(px + size - cornerSize + 1, py - 1, cornerSize, 2);
    c.fillRect(px + size - 1, py - 1, 2, cornerSize);

    // Bottom-left corner
    c.fillRect(px - 1, py + size - 1, cornerSize, 2);
    c.fillRect(px - 1, py + size - cornerSize + 1, 2, cornerSize);

    // Bottom-right corner
    c.fillRect(px + size - cornerSize + 1, py + size - 1, cornerSize, 2);
    c.fillRect(px + size - 1, py + size - cornerSize + 1, 2, cornerSize);

    // Dark outlines for corners
    c.fillStyle = '#000000';

    // Top-left
    c.fillRect(px - 2, py - 2, cornerSize + 1, 1);
    c.fillRect(px - 2, py - 2, 1, cornerSize + 1);

    // Top-right
    c.fillRect(px + size - cornerSize, py - 2, cornerSize + 2, 1);
    c.fillRect(px + size + 1, py - 2, 1, cornerSize + 1);

    // Bottom-left
    c.fillRect(px - 2, py + size + 1, cornerSize + 1, 1);
    c.fillRect(px - 2, py + size - cornerSize, 1, cornerSize + 2);

    // Bottom-right
    c.fillRect(px + size - cornerSize, py + size + 1, cornerSize + 2, 1);
    c.fillRect(px + size + 1, py + size - cornerSize, 1, cornerSize + 2);
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
    // Left-click (0) or Right-click (2) to pan
    if (event.button === 0 || event.button === 2) {
        dragButton.value = event.button;
        dragStart.value = { x: event.clientX, y: event.clientY };
        viewportStart.value = { x: viewport.x, y: viewport.y };

        if (event.button === 0) {
            isDragging.value = true;
        } else {
            isPanning.value = true;
        }
    }
}

function handleMouseMove(event: MouseEvent) {
    // Pan with either left-drag or right-drag
    if (isDragging.value || isPanning.value) {
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
    // Handle left-click release - may place pixel if didn't drag
    if (event.button === 0 && isDragging.value) {
        const dx = Math.abs(event.clientX - dragStart.value.x);
        const dy = Math.abs(event.clientY - dragStart.value.y);

        // Only trigger click if we didn't drag much
        if (dx < 5 && dy < 5 && hoveredPixel.value && props.canPlace) {
            emit('pixelClick', hoveredPixel.value.x, hoveredPixel.value.y);
        }
        isDragging.value = false;
    }

    // Handle right-click release - just stop panning
    if (event.button === 2 && isPanning.value) {
        isPanning.value = false;
    }
}

function handleMouseLeave() {
    isDragging.value = false;
    isPanning.value = false;
    hoveredPixel.value = null;
    requestRender();
}

function handleWheel(event: WheelEvent) {
    const { x: canvasX, y: canvasY } = screenToCanvas(event.clientX, event.clientY);

    const delta = event.deltaY > 0 ? 0.85 : 1.18;
    const oldZoom = viewport.zoom;
    const newZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, viewport.zoom * delta));

    // Zoom towards cursor position
    // Keep the canvas point under the cursor at the same screen position
    const zoomRatio = oldZoom / newZoom;
    viewport.x = canvasX + (viewport.x - canvasX) * zoomRatio;
    viewport.y = canvasY + (viewport.y - canvasY) * zoomRatio;
    viewport.zoom = newZoom;

    clampViewport();
    requestRender();
}

// Touch handlers
const lastTouchPos = ref({ x: 0, y: 0 });

function handleTouchStart(event: TouchEvent) {
    if (event.touches.length === 1) {
        const touch = event.touches[0];
        isDragging.value = true;
        dragStart.value = { x: touch.clientX, y: touch.clientY };
        lastTouchPos.value = { x: touch.clientX, y: touch.clientY };
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
        lastTouchPos.value = { x: touch.clientX, y: touch.clientY };
        const dx = touch.clientX - dragStart.value.x;
        const dy = touch.clientY - dragStart.value.y;
        viewport.x = viewportStart.value.x - dx / viewport.zoom;
        viewport.y = viewportStart.value.y - dy / viewport.zoom;
        clampViewport();
        requestRender();

        // Update hovered pixel during drag
        const { x, y } = screenToCanvas(touch.clientX, touch.clientY);
        if (x >= 0 && x < props.config.width && y >= 0 && y < props.config.height) {
            hoveredPixel.value = { x, y };
        }
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
        if (isDragging.value && hoveredPixel.value) {
            // Check if this was a tap (minimal movement) vs a drag
            const dx = Math.abs(lastTouchPos.value.x - dragStart.value.x);
            const dy = Math.abs(lastTouchPos.value.y - dragStart.value.y);
            if (dx < 10 && dy < 10) {
                // Emit pixelTap for touch selection (let parent handle placement UI)
                emit('pixelTap', hoveredPixel.value.x, hoveredPixel.value.y);
                // Keep hoveredPixel set and re-render to show the selection
                requestRender();
            } else {
                // Was a drag, clear the hover
                hoveredPixel.value = null;
            }
        }
        isDragging.value = false;
        lastPinchDistance.value = 0;
        requestRender();
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

// Watch for selected pixel changes (mobile)
watch(
    () => props.selectedPixel,
    () => {
        requestRender();
    }
);

// Expose methods
defineExpose({
    updatePixel,
    requestRender,
});
</script>
