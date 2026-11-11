<script setup>
defineProps({ title: String, wide: Boolean, pill: String });
const emit = defineEmits(['close']);
</script>

<template>
    <div class="fixed inset-0 z-50 grid place-items-center p-[18px]" role="dialog" aria-modal="true" @keydown.esc="emit('close')">
        <button class="absolute inset-0 bg-[var(--scrim)]" aria-label="Close" @click="emit('close')"></button>
        <div class="anim-up relative grid max-h-[92dvh] w-full gap-4 overflow-y-auto rounded-2xl bg-surface p-6 shadow-float" :class="wide ? 'max-w-2xl' : 'max-w-[460px]'">
            <div class="flex items-start justify-between gap-3">
                <h2 class="m-0 text-[20px] font-extrabold">{{ title }}</h2>
                <span v-if="pill" class="demo-pill">{{ pill }}</span>
                <button v-else class="grid h-8 w-8 flex-none place-items-center rounded-lg border border-line bg-surface-2" aria-label="Close" @click="emit('close')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
            <slot />
        </div>
    </div>
</template>
