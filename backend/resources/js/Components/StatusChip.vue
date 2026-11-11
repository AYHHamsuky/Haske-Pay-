<script setup>
import { t } from '@/lib/i18n';
defineProps({ status: { type: String, required: true } });
</script>

<template>
    <span class="chip" :class="'chip-' + status">{{ t(status) }}</span>
</template>
