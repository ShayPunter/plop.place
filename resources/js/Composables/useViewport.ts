import { ref, computed, reactive, onMounted, onUnmounted } from 'vue';

export interface ViewportState {
    x: number;
    y: number;
    zoom: number;
}

export interface ViewportOptions {
    canvasWidth: number;
    canvasHeight: number;
    minZoom?: number;
    maxZoom?: number;
    initialZoom?: number;
}

export function useViewport(options: ViewportOptions) {
    const {
        canvasWidth,
        canvasHeight,
        minZoom = 0.5,
        maxZoom = 40,
        initialZoom = 4,
    } = options;

    const viewport = reactive<ViewportState>({
        x: canvasWidth / 2,
        y: canvasHeight / 2,
        zoom: initialZoom,
    });

    const isDragging = ref(false);
    const lastMousePos = ref({ x: 0, y: 0 });

    const pixelSize = computed(() => viewport.zoom);

    function screenToCanvas(screenX: number, screenY: number, containerRect: DOMRect): { x: number; y: number } {
        const centerX = containerRect.width / 2;
        const centerY = containerRect.height / 2;

        const canvasX = viewport.x + (screenX - containerRect.left - centerX) / viewport.zoom;
        const canvasY = viewport.y + (screenY - containerRect.top - centerY) / viewport.zoom;

        return {
            x: Math.floor(canvasX),
            y: Math.floor(canvasY),
        };
    }

    function canvasToScreen(canvasX: number, canvasY: number, containerRect: DOMRect): { x: number; y: number } {
        const centerX = containerRect.width / 2;
        const centerY = containerRect.height / 2;

        return {
            x: centerX + (canvasX - viewport.x) * viewport.zoom,
            y: centerY + (canvasY - viewport.y) * viewport.zoom,
        };
    }

    function zoom(delta: number, centerX?: number, centerY?: number): void {
        const oldZoom = viewport.zoom;
        const newZoom = Math.min(maxZoom, Math.max(minZoom, viewport.zoom * (1 + delta)));

        if (centerX !== undefined && centerY !== undefined) {
            // Zoom towards point
            const factor = 1 - newZoom / oldZoom;
            viewport.x += (centerX - viewport.x) * factor;
            viewport.y += (centerY - viewport.y) * factor;
        }

        viewport.zoom = newZoom;
    }

    function zoomIn(): void {
        zoom(0.25);
    }

    function zoomOut(): void {
        zoom(-0.2);
    }

    function pan(deltaX: number, deltaY: number): void {
        viewport.x -= deltaX / viewport.zoom;
        viewport.y -= deltaY / viewport.zoom;

        // Clamp to canvas bounds with some padding
        const padding = 100;
        viewport.x = Math.max(-padding, Math.min(canvasWidth + padding, viewport.x));
        viewport.y = Math.max(-padding, Math.min(canvasHeight + padding, viewport.y));
    }

    function centerOn(x: number, y: number): void {
        viewport.x = x;
        viewport.y = y;
    }

    function resetView(): void {
        viewport.x = canvasWidth / 2;
        viewport.y = canvasHeight / 2;
        viewport.zoom = initialZoom;
    }

    function startDrag(x: number, y: number): void {
        isDragging.value = true;
        lastMousePos.value = { x, y };
    }

    function drag(x: number, y: number): void {
        if (!isDragging.value) return;

        const deltaX = x - lastMousePos.value.x;
        const deltaY = y - lastMousePos.value.y;

        pan(deltaX, deltaY);

        lastMousePos.value = { x, y };
    }

    function endDrag(): void {
        isDragging.value = false;
    }

    function handleWheel(event: WheelEvent, containerRect: DOMRect): void {
        event.preventDefault();

        const { x: canvasX, y: canvasY } = screenToCanvas(event.clientX, event.clientY, containerRect);

        const delta = event.deltaY > 0 ? -0.1 : 0.1;
        zoom(delta, canvasX, canvasY);
    }

    return {
        viewport,
        pixelSize,
        isDragging,
        screenToCanvas,
        canvasToScreen,
        zoom,
        zoomIn,
        zoomOut,
        pan,
        centerOn,
        resetView,
        startDrag,
        drag,
        endDrag,
        handleWheel,
    };
}
