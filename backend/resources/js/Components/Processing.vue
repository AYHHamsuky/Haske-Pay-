<script setup>
import { naira } from '@/lib/format';
import { t } from '@/lib/i18n';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ amount: Number, title: String, target: String });
const step = ref(0);
let timers = [];
onMounted(() => (timers = [setTimeout(() => (step.value = 1), 700), setTimeout(() => (step.value = 2), 1600)]));
onBeforeUnmount(() => timers.forEach(clearTimeout));
</script>

<template>
    <div class="fixed inset-0 z-50 grid place-content-center justify-items-center gap-4 bg-surface p-6 text-center" role="alertdialog" aria-live="assertive">
        <div class="spinner" aria-hidden="true"></div>
        <h3 class="m-0 text-[21px] font-extrabold">{{ t('processing') }} {{ naira(amount) }}</h3>
        <p class="m-0 text-muted">{{ title }} · <span class="mono">{{ target }}</span></p>
        <ul class="mt-1 grid gap-1.5 text-left text-[13px]">
            <li class="flex items-center gap-2 text-good"><i class="h-2 w-2 rounded-full bg-current"></i>Wallet debited</li>
            <li class="flex items-center gap-2" :class="step >= 1 ? 'text-good' : 'font-semibold text-ink'"><i class="h-2 w-2 rounded-full bg-current"></i>Sent to provider</li>
            <li class="flex items-center gap-2" :class="step >= 2 ? 'font-semibold text-ink' : 'text-muted'"><i class="h-2 w-2 rounded-full bg-current"></i>Waiting for confirmation</li>
        </ul>
    </div>
</template>
