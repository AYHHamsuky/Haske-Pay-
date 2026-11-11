<script setup>
import { tokenGroups } from '@/lib/format';
import { ref } from 'vue';

const props = defineProps({ token: String, short: String, units: String, meter: String });
const copied = ref(false);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.token);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1800);
    } catch {}
}
</script>

<template>
    <div class="token-box" aria-label="Electricity token">
        <div class="t-label">{{ short ? short + ' · ' : '' }}ELECTRICITY TOKEN</div>
        <div class="t-digits select-all">{{ tokenGroups(token) }}</div>
        <div class="t-meta"><span>Units <b>{{ units || '—' }}</b></span><span v-if="meter">Meter <b>{{ meter }}</b></span></div>
    </div>
    <button type="button" class="btn mt-2.5 w-full" @click="copy">{{ copied ? 'Copied ✓' : 'Copy token' }}</button>
</template>
