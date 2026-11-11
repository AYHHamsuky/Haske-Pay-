<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({ label: String, value: [String, Number], note: String, tone: String, icon: { type: String, default: 'chart' } });
</script>

<template>
    <div class="stat">
        <div class="stat-top">{{ label }} <span class="stat-icon"><Icon :name="icon" :size="17" /></span></div>
        <strong>{{ value }}</strong>
        <small :class="{ 'font-semibold text-good': tone === 'good', 'font-semibold text-bad': tone === 'bad', 'font-semibold text-warn': tone === 'warn' }">{{ note }}</small>
        <slot />
    </div>
</template>
