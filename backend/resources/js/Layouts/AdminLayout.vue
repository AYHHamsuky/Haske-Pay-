<script setup>
import Shell from '@/Layouts/Shell.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ title: String, subtitle: String });
const page = usePage();
const groups = computed(() => [
    { label: 'OPERATIONS', items: [
        { label: 'Dashboard', href: '/admin', match: '/admin', icon: 'grid', exact: true },
        { label: 'Transactions', href: '/admin/transactions', match: '/admin/transactions', icon: 'list', badge: page.props.escalated || null },
        { label: 'Users & wallets', href: '/admin/users', match: '/admin/users', icon: 'users' },
        { label: 'Support', href: '/admin/support', match: '/admin/support', icon: 'help', badge: page.props.openTickets || null },
    ] },
    { label: 'CONTROLS', items: [
        { label: 'Services', href: '/admin/services', match: '/admin/services', icon: 'switch' },
        { label: 'Reconciliation', href: '/admin/reconciliation', match: '/admin/reconciliation', icon: 'scale' },
        { label: 'Audit log', href: '/admin/audit', match: '/admin/audit', icon: 'shield' },
        ...(page.props.billsSandbox ? [{ label: 'Sandbox', href: '/admin/sandbox', match: '/admin/sandbox', icon: 'flask' }] : []),
    ] },
]);
</script>

<template>
    <Shell :groups="groups" crumb-root="Admin" :title="title" :subtitle="subtitle">
        <template v-if="$slots.actions" #actions><slot name="actions" /></template>
        <slot />
    </Shell>
</template>
