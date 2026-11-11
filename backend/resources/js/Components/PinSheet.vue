<script setup>
import { t } from '@/lib/i18n';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ label: String, error: String, busy: Boolean });
const emit = defineEmits(['submit', 'close']);
const pin = ref('');
const shake = ref(false);

watch(() => props.error, (e) => {
    if (e) {
        pin.value = '';
        shake.value = true;
        setTimeout(() => (shake.value = false), 400);
    }
});

function press(k) {
    if (props.busy) return;
    if (k === '⌫') pin.value = pin.value.slice(0, -1);
    else if (pin.value.length < 4) pin.value += k;
    if (pin.value.length === 4) emit('submit', pin.value);
}

function onKey(e) {
    if (/^\d$/.test(e.key)) press(e.key);
    else if (e.key === 'Backspace') press('⌫');
    else if (e.key === 'Escape') emit('close');
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-labelledby="pinH">
        <button class="absolute inset-0 bg-[var(--scrim)]" aria-label="Close" @click="emit('close')"></button>
        <div class="anim-up relative grid w-full max-w-[400px] gap-4 rounded-t-[22px] bg-surface px-5 pt-5 pb-6 sm:rounded-[22px]">
            <div>
                <h3 id="pinH" class="m-0 text-xl font-bold">{{ t('pin') }}</h3>
                <p class="mt-1 text-[13px] text-muted">{{ label }}</p>
            </div>
            <div class="flex justify-center gap-3.5 py-1" :class="{ 'anim-shake': shake }">
                <i v-for="i in 4" :key="i" class="block h-3.5 w-3.5 rounded-full border-2 border-accent" :class="{ 'bg-accent': i <= pin.length }"></i>
            </div>
            <p v-if="error" class="m-0 text-center text-[13px] font-medium text-bad" role="alert">{{ error }}</p>
            <div class="grid grid-cols-3 gap-2">
                <template v-for="k in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫']" :key="k">
                    <span v-if="!k"></span>
                    <button v-else type="button" class="rounded-[14px] bg-surface-2 py-3.5 font-display text-[22px] font-semibold active:bg-accent-soft disabled:opacity-50" :disabled="busy" :aria-label="k === '⌫' ? 'Delete' : k" @click="press(k)">{{ k }}</button>
                </template>
            </div>
        </div>
    </div>
</template>
