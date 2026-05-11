<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const toasts = ref([]);
let idCounter = 0;

function addToast(toast) {
    const id = ++idCounter;
    toasts.value.push({ id, ...toast });
    setTimeout(() => removeToast(id), toast.duration || 4000);
}

function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

// Watch Inertia flash messages
const page = usePage();
let lastFlash = '';

function checkFlash() {
    const flash = page.props.flash;
    if (!flash) return;
    const msg = flash.success || flash.error || flash.warning || flash.info;
    if (msg && msg !== lastFlash) {
        lastFlash = msg;
        addToast({
            type: flash.success ? 'success' : flash.error ? 'error' : flash.warning ? 'warning' : 'info',
            message: msg,
        });
    }
}

// Expose globally
if (typeof window !== 'undefined') {
    window.__toast = addToast;
}

onMounted(() => {
    checkFlash();
    const interval = setInterval(checkFlash, 300);
    onUnmounted(() => clearInterval(interval));
});

const iconPaths = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};

const colorClasses = {
    success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
    error: 'border-red-500/30 bg-red-500/10 text-red-400',
    warning: 'border-amber-500/30 bg-amber-500/10 text-amber-400',
    info: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-400',
};

const iconColor = {
    success: 'text-emerald-400',
    error: 'text-red-400',
    warning: 'text-amber-400',
    info: 'text-cyan-400',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style="min-width:320px;max-width:420px">
            <TransitionGroup
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="translate-x-8 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-8 opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[
                        'pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-lg border backdrop-blur-xl shadow-2xl',
                        colorClasses[toast.type] || colorClasses.info
                    ]"
                >
                    <svg :class="['w-5 h-5 mt-0.5 shrink-0', iconColor[toast.type]]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPaths[toast.type] || iconPaths.info" />
                    </svg>
                    <p class="text-sm font-medium flex-1">{{ toast.message }}</p>
                    <button @click="removeToast(toast.id)" class="shrink-0 p-0.5 hover:opacity-70 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
