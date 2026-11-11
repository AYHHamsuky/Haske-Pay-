<script setup>
import Shell from '@/Layouts/Shell.vue';
import { t } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ title: String, subtitle: String });
const page = usePage();
const groups = computed(() => {
    const work = [
        { label: t('overview'), href: '/app', match: '/app', icon: 'grid', exact: true },
        { label: t('payBill'), href: '/app/buy', match: '/app/buy', icon: 'arrow-up-right' },
        { label: t('myWallet'), href: '/app/wallet', match: '/app/wallet', icon: 'wallet' },
        { label: t('transactions'), href: '/app/history', match: '/app/history', icon: 'list' },
    ];
    if (page.props.auth?.role === 'agent') {
        work.push({ label: t('agentDashboard'), href: '/agent', match: '/agent', icon: 'store', exact: true });
        work.push({ label: t('bulkVending'), href: '/agent/bulk', match: '/agent/bulk', icon: 'layers' });
        work.push({ label: t('commission'), href: '/agent/commission', match: '/agent/commission', icon: 'chart' });
    }
    return [
        { label: 'WORKSPACE', items: work },
        { label: 'ACCOUNT', items: [
            { label: t('profileSecurity'), href: '/app/profile', match: '/app/profile', icon: 'settings' },
            { label: t('help'), href: '/app/help', match: '/app/help', icon: 'help' },
        ] },
    ];
});
</script>

<template>
    <Shell :groups="groups" :title="title" :subtitle="subtitle">
        <template v-if="$slots.actions" #actions><slot name="actions" /></template>
        <slot />
    </Shell>
</template>
