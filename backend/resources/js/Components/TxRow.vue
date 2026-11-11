<script setup>
import Icon from '@/Components/Icon.vue';
import StatusChip from '@/Components/StatusChip.vue';
import { naira, when } from '@/lib/format';
import { lang, t } from '@/lib/i18n';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ tx: Object, href: String });
const title = computed(() => {
    const x = props.tx;
    const kind = x.category === 'electricity' ? t('elec') : x.category === 'data' ? t('data') : t('airtime');
    return `${kind} · ${x.service_name}${x.plan ? ' ' + x.plan : ''}`;
});
const icon = computed(() => ({ electricity: ['bolt', ''], airtime: ['phone', 'orange'], data: ['wifi', 'blue'] })[props.tx.category] || ['bolt', '']);
</script>

<template>
    <Link :href="href" class="activity-row -mx-2 rounded-xl px-2 hover:bg-surface-2">
        <span class="icon-tile !mb-0 !h-[38px] !w-[38px] !rounded-[11px]" :class="icon[1]"><Icon :name="icon[0]" :size="18" /></span>
        <span class="min-w-0 flex-1">
            <strong class="block truncate text-[12.5px] font-bold text-heading">{{ title }}</strong>
            <small class="text-[11px] text-muted">{{ when(tx.created_at, lang) }} · {{ tx.reference }}</small>
        </span>
        <span class="text-right text-[12.5px] font-extrabold whitespace-nowrap num">
            {{ naira(tx.total) }}
            <small class="mt-1 block"><StatusChip :status="tx.status" /></small>
        </span>
    </Link>
</template>
