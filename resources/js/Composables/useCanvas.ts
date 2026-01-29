import { ref, computed, onMounted, onUnmounted } from 'vue';

export interface CanvasConfig {
    width: number;
    height: number;
    palette: Record<number, string>;
    cooldownSeconds: number;
}

export function useCanvas(config: CanvasConfig) {
    const canvasData = ref<Uint8Array | null>(null);
    const loading = ref(true);
    const error = ref<string | null>(null);

    const totalPixels = computed(() => config.width * config.height);

    async function loadCanvas(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch('/api/canvas');
            if (!response.ok) {
                throw new Error('Failed to load canvas');
            }

            const buffer = await response.arrayBuffer();
            canvasData.value = new Uint8Array(buffer);
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Unknown error';
        } finally {
            loading.value = false;
        }
    }

    function getPixel(x: number, y: number): number {
        if (!canvasData.value) return 15; // Default white

        const index = y * config.width + x;
        const byteIndex = Math.floor(index / 2);
        const nibbleIndex = index % 2;

        if (byteIndex >= canvasData.value.length) return 15;

        const byte = canvasData.value[byteIndex];
        return nibbleIndex === 0 ? (byte >> 4) & 0x0f : byte & 0x0f;
    }

    function setPixel(x: number, y: number, color: number): void {
        if (!canvasData.value) return;

        const index = y * config.width + x;
        const byteIndex = Math.floor(index / 2);
        const nibbleIndex = index % 2;

        if (byteIndex >= canvasData.value.length) return;

        const byte = canvasData.value[byteIndex];
        if (nibbleIndex === 0) {
            canvasData.value[byteIndex] = (color << 4) | (byte & 0x0f);
        } else {
            canvasData.value[byteIndex] = (byte & 0xf0) | (color & 0x0f);
        }
    }

    function getColorHex(colorIndex: number): string {
        return config.palette[colorIndex] || '#FFFFFF';
    }

    return {
        canvasData,
        loading,
        error,
        totalPixels,
        loadCanvas,
        getPixel,
        setPixel,
        getColorHex,
    };
}
