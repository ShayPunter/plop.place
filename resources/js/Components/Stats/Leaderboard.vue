<template>
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="divide-y divide-gray-700">
            <div
                v-for="(user, index) in users"
                :key="user.id + '-' + user.type"
                class="flex items-center gap-4 p-4 hover:bg-gray-700/50 transition-colors"
            >
                <!-- Rank -->
                <div
                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                    :class="getRankClass(index)"
                >
                    {{ index + 1 }}
                </div>

                <!-- Name -->
                <div class="flex-1 min-w-0">
                    <p
                        class="font-medium truncate"
                        :class="user.type === 'anonymous' ? 'text-gray-400' : 'text-white'"
                    >
                        {{ user.name }}
                    </p>
                    <p v-if="user.type === 'anonymous'" class="text-xs text-gray-500">
                        Anonymous
                    </p>
                </div>

                <!-- Pixel count -->
                <div class="text-right flex-shrink-0">
                    <p class="font-mono text-blue-400">{{ formatNumber(user.pixels_placed) }}</p>
                    <p class="text-xs text-gray-500">pixels</p>
                </div>
            </div>

            <div v-if="!users.length" class="p-8 text-center text-gray-500">
                No data yet
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
interface User {
    id: number;
    name: string;
    pixels_placed: number;
    type: 'user' | 'anonymous';
}

defineProps<{
    users: User[];
}>();

function getRankClass(index: number): string {
    if (index === 0) return 'bg-yellow-500 text-yellow-900';
    if (index === 1) return 'bg-gray-300 text-gray-800';
    if (index === 2) return 'bg-amber-600 text-amber-100';
    return 'bg-gray-700 text-gray-300';
}

function formatNumber(num: number): string {
    return num.toLocaleString();
}
</script>
