import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}

export interface PixelUpdate {
    x: number;
    y: number;
    color: number;
    timestamp: number;
}

export function useWebSocket() {
    const connected = ref(false);
    const echo = ref<Echo | null>(null);
    const pixelUpdates = ref<PixelUpdate[]>([]);
    const onPixelPlacedCallbacks: ((pixel: PixelUpdate) => void)[] = [];

    function initialize(): void {
        window.Pusher = Pusher;

        echo.value = new Echo({
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
            wsHost: import.meta.env.VITE_PUSHER_HOST ?? window.location.hostname,
            wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
            wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
            forceTLS: import.meta.env.VITE_PUSHER_SCHEME === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
        });

        window.Echo = echo.value;

        // Subscribe to canvas channel
        echo.value.channel('canvas').listen('.pixel.placed', (data: PixelUpdate) => {
            pixelUpdates.value.push(data);
            onPixelPlacedCallbacks.forEach((cb) => cb(data));
        });

        // Track connection state
        echo.value.connector.pusher.connection.bind('connected', () => {
            connected.value = true;
        });

        echo.value.connector.pusher.connection.bind('disconnected', () => {
            connected.value = false;
        });
    }

    function onPixelPlaced(callback: (pixel: PixelUpdate) => void): void {
        onPixelPlacedCallbacks.push(callback);
    }

    function disconnect(): void {
        if (echo.value) {
            echo.value.disconnect();
            echo.value = null;
        }
        connected.value = false;
    }

    onUnmounted(() => {
        disconnect();
    });

    return {
        connected,
        pixelUpdates,
        initialize,
        onPixelPlaced,
        disconnect,
    };
}
