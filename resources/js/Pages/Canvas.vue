<template>
    <div class="h-screen w-screen overflow-hidden bg-gray-900">
        <!-- Main Canvas -->
        <CanvasRenderer
            ref="canvasRef"
            :config="canvasConfig"
            :canvas-data="canvasData"
            :loading="loading"
            :selected-color="selectedColor"
            :can-place="canPlace"
            @pixel-click="handlePixelClick"
            @pixel-hover="handlePixelHover"
        />

        <!-- Top bar -->
        <div class="absolute top-2 sm:top-4 left-2 sm:left-4 flex items-center gap-2 sm:gap-4 z-20">
            <h1 class="text-white text-xl sm:text-2xl font-bold tracking-tight drop-shadow-lg">
                Plop.Place
            </h1>
            <Link
                href="/stats"
                class="text-gray-400 hover:text-white text-xs sm:text-sm bg-gray-900/80 px-2 sm:px-3 py-1 rounded transition-colors"
            >
                Stats
            </Link>
            <div
                v-if="wsConnected"
                class="flex items-center gap-1 sm:gap-2 text-green-400 text-xs sm:text-sm bg-gray-900/80 px-2 py-1 rounded"
            >
                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse" />
                <span class="hidden sm:inline">Live</span>
            </div>
            <div v-else class="flex items-center gap-1 sm:gap-2 text-yellow-400 text-xs sm:text-sm bg-gray-900/80 px-2 py-1 rounded">
                <span class="w-2 h-2 bg-yellow-400 rounded-full" />
                <span class="hidden sm:inline">Connecting...</span>
            </div>
        </div>

        <!-- User info -->
        <div class="absolute top-2 sm:top-4 right-2 sm:right-4 z-20">
            <div
                v-if="auth.user"
                class="bg-gray-900/90 backdrop-blur-sm rounded-lg px-2 sm:px-4 py-1 sm:py-2 text-white text-xs sm:text-sm"
            >
                <span class="text-gray-400">{{ auth.user.name }}</span>
                <span class="mx-1 sm:mx-2 text-gray-600">|</span>
                <span class="text-blue-400">{{ auth.user.pixels_placed }} <span class="hidden sm:inline">pixels</span><span class="sm:hidden">px</span></span>
            </div>
            <div v-else class="bg-gray-900/90 backdrop-blur-sm rounded-lg px-2 sm:px-4 py-1 sm:py-2 text-gray-400 text-xs sm:text-sm">
                <span class="hidden sm:inline">Playing anonymously</span>
                <span class="sm:hidden">Anonymous</span>
            </div>
        </div>

        <!-- Bottom toolbar -->
        <div class="absolute bottom-2 sm:bottom-4 left-1/2 transform -translate-x-1/2 flex flex-col sm:flex-row items-center gap-2 sm:gap-4 z-20 w-full sm:w-auto px-2 sm:px-0">
            <!-- Color palette -->
            <ColorPalette
                :palette="canvasConfig.palette"
                :selected-color="selectedColor"
                @select="selectedColor = $event"
            />

            <!-- Cooldown timer -->
            <CooldownTimer
                :cooldown-end="cooldownEnd"
                :cooldown-duration="canvasConfig.cooldownSeconds"
                @complete="handleCooldownComplete"
            />
        </div>

        <!-- Pixel info (bottom right) - hidden on mobile to avoid overlap -->
        <div class="absolute bottom-2 sm:bottom-4 right-2 sm:right-4 z-20 hidden sm:block">
            <PixelInfo
                :x="hoveredX"
                :y="hoveredY"
                :color="hoveredColor"
                :palette="canvasConfig.palette"
            />
        </div>

        <!-- Error toast -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="error"
                class="absolute top-20 left-1/2 transform -translate-x-1/2 bg-red-600 text-white px-6 py-3 rounded-lg shadow-xl z-30"
            >
                {{ error }}
            </div>
        </Transition>

        <!-- Help tooltip -->
        <div class="absolute top-2 sm:top-4 left-1/2 transform -translate-x-1/2 text-gray-500 text-xs z-20 text-center px-2">
            {{ helpText }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import CanvasRenderer from '@/Components/Canvas/CanvasRenderer.vue';
import ColorPalette from '@/Components/Pixel/ColorPalette.vue';
import CooldownTimer from '@/Components/Pixel/CooldownTimer.vue';
import PixelInfo from '@/Components/Pixel/PixelInfo.vue';
import { useCanvas } from '@/Composables/useCanvas';
import { useWebSocket } from '@/Composables/useWebSocket';

interface Props {
    canvasConfig: {
        width: number;
        height: number;
        palette: Record<number, string>;
        cooldownSeconds: number;
    };
    initialState: {
        canPlace: boolean;
        cooldownEnd: number | null;
        sessionToken: string | null;
    };
    auth: {
        user: {
            id: number;
            name: string;
            pixels_placed: number;
        } | null;
    };
}

const props = defineProps<Props>();

// Canvas state
const canvasRef = ref<InstanceType<typeof CanvasRenderer> | null>(null);
const {
    canvasData,
    loading,
    loadCanvas,
    setPixel,
} = useCanvas(props.canvasConfig);

// WebSocket
const { connected: wsConnected, initialize: initWebSocket, onPixelPlaced } = useWebSocket();

// UI state
const selectedColor = ref(1); // Start with red
const cooldownEnd = ref<number | null>(props.initialState.cooldownEnd);
const canPlace = computed(() => !cooldownEnd.value || cooldownEnd.value <= Math.floor(Date.now() / 1000));
const error = ref<string | null>(null);
const sessionToken = ref<string | null>(props.initialState.sessionToken);

// Hover state
const hoveredX = ref<number | null>(null);
const hoveredY = ref<number | null>(null);
const hoveredColor = ref(15);

// Touch detection
const isTouchDevice = ref(false);
const helpText = computed(() => {
    if (isTouchDevice.value) {
        return 'Pinch to zoom | Drag to pan | Tap to place pixel';
    }
    return 'Scroll to zoom | Right-click drag to pan | Left-click to place pixel';
});

// Track when we last refreshed to avoid rapid refreshes
let lastRefreshTime = 0;
const REFRESH_COOLDOWN_MS = 5000; // Don't refresh more than once every 5 seconds

async function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        const now = Date.now();
        if (now - lastRefreshTime < REFRESH_COOLDOWN_MS) {
            return;
        }
        lastRefreshTime = now;

        // Reload canvas data to catch any missed updates
        await loadCanvas();

        // Force re-render
        canvasRef.value?.requestRender();
    }
}

async function ensureSession(): Promise<string | null> {
    if (props.auth.user) return null;

    if (sessionToken.value) return sessionToken.value;

    try {
        const response = await fetch('/api/auth/anonymous', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
        });

        if (response.ok) {
            const data = await response.json();
            sessionToken.value = data.token;
            return data.token;
        }
    } catch (e) {
        console.error('Failed to create session:', e);
    }

    return null;
}

async function handlePixelClick(x: number, y: number) {
    if (!canPlace.value) {
        showError('Please wait for cooldown');
        return;
    }

    const token = await ensureSession();

    try {
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        };

        if (token) {
            headers['X-Session-Token'] = token;
        }

        const response = await fetch('/api/pixel', {
            method: 'POST',
            headers,
            body: JSON.stringify({
                x,
                y,
                color: selectedColor.value,
            }),
        });

        const data = await response.json();

        if (data.success) {
            setPixel(x, y, selectedColor.value);
            canvasRef.value?.updatePixel(x, y, selectedColor.value);
            cooldownEnd.value = data.cooldown_end;
        } else {
            showError(data.message || 'Failed to place pixel');

            if (data.error === 'cooldown') {
                cooldownEnd.value = Math.floor(Date.now() / 1000) + data.remaining;
            }
        }
    } catch (e) {
        showError('Network error. Please try again.');
        console.error('Pixel placement error:', e);
    }
}

function handlePixelHover(x: number, y: number, color: number) {
    hoveredX.value = x;
    hoveredY.value = y;
    hoveredColor.value = color;
}

function handleCooldownComplete() {
    cooldownEnd.value = null;
}

function showError(message: string) {
    error.value = message;
    setTimeout(() => {
        error.value = null;
    }, 3000);
}

onMounted(async () => {
    // Detect touch device
    isTouchDevice.value = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    await loadCanvas();
    initWebSocket();

    onPixelPlaced((pixel) => {
        setPixel(pixel.x, pixel.y, pixel.color);
        canvasRef.value?.updatePixel(pixel.x, pixel.y, pixel.color);
    });

    if (!props.auth.user) {
        await ensureSession();
    }

    // Refresh canvas when user returns to tab (catches missed WebSocket updates)
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>
