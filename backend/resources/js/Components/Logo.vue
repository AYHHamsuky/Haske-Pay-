<script setup>
import { brand } from '@/lib/brand';

defineProps({ size: { type: Number, default: 40 }, tagline: { type: Boolean, default: true }, light: Boolean });
</script>

<template>
    <span class="inline-flex items-center gap-[11px]">
        <span class="grid flex-none place-items-center rounded-[12px] bg-[#08A56B] font-black text-white shadow-[0_6px_14px_#07945f25]" :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.48 + 'px' }" aria-hidden="true">₦</span>
        <span class="leading-none">
            <strong class="block text-[18px] font-extrabold tracking-[-.02em]" :class="light ? 'text-white' : 'text-heading'">{{ brand.name }}</strong>
            <small v-if="tagline" class="mt-[5px] block text-[9.5px] font-semibold tracking-[.06em] whitespace-nowrap" :class="light ? 'text-navy-muted' : 'text-muted'">{{ brand.tagline }}</small>
        </span>
    </span>
</template>
