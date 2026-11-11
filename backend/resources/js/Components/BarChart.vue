<script setup>
import { shortDay } from '@/lib/format';
import { computed, ref } from 'vue';

/**
 * Stacked bars over days. series: [{key,label,color}], rows: [{day, ...values}].
 * Bars stretch to the container; labels are HTML so text never scales with width.
 */
const props = defineProps({
    rows: { type: Array, required: true },
    series: { type: Array, required: true },
    format: { type: Function, default: (v) => String(v) },
    height: { type: Number, default: 180 },
});
const hover = ref(null);
const totals = computed(() => props.rows.map((r) => props.series.reduce((s, x) => s + (r[x.key] || 0), 0)));
const max = computed(() => Math.max(1, ...totals.value));
const pct = (v) => (v / max.value) * 100;
const every = computed(() => Math.ceil(props.rows.length / 10));
</script>

<template>
    <div class="relative">
        <div class="flex items-end gap-[3px] border-b border-line" :style="{ height: height + 'px' }" role="img" :aria-label="series.map((s) => s.label).join(', ') + ' by day'">
            <div v-for="(r, i) in rows" :key="r.day" class="flex h-full flex-1 flex-col-reverse items-center" @mouseenter="hover = i" @mouseleave="hover = null">
                <div v-for="s in series" :key="s.key" class="w-[64%] max-w-[36px] first:rounded-b-[2px] last:rounded-t-[4px] transition-opacity"
                    :style="{ height: pct(r[s.key] || 0) + '%', background: s.color, opacity: hover === null || hover === i ? 1 : 0.45 }"></div>
            </div>
        </div>
        <div class="flex gap-[3px] pt-1.5 text-[11px] text-muted">
            <span v-for="(r, i) in rows" :key="r.day" class="flex-1 truncate text-center">{{ i % every !== 0 ? '' : i === 0 || r.day.endsWith('-01') ? shortDay(r.day) : Number(r.day.slice(8)) }}</span>
        </div>
        <div v-if="hover !== null" class="pointer-events-none absolute top-0 z-10 rounded-lg border border-line bg-surface px-3 py-2 text-[12px] shadow-float" :style="{ left: `clamp(0px, calc(${((hover + 0.5) / rows.length) * 100}% - 70px), calc(100% - 160px))` }">
            <b class="block">{{ shortDay(rows[hover].day) }}</b>
            <span v-for="s in series" :key="s.key" class="flex items-center gap-1.5"><i class="h-2 w-2 rounded-sm" :style="{ background: s.color }"></i>{{ s.label }}: <b class="num">{{ format(rows[hover][s.key] || 0) }}</b></span>
        </div>
        <div class="mt-2 flex flex-wrap gap-3 text-[12px] text-muted">
            <span v-for="s in series" :key="s.key" class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm" :style="{ background: s.color }"></i>{{ s.label }}</span>
        </div>
    </div>
</template>
