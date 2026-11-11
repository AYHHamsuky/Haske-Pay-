<script setup>
import StatusChip from '@/Components/StatusChip.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira, tokenGroups } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ batch: Object, service: Object, transactions: Array, errors: Array });
let timer;
const running = computed(() => props.batch.status !== 'done' || props.transactions.some((t) => t.status === 'pending'));
onMounted(() => (timer = setInterval(() => running.value && router.reload({ only: ['batch', 'transactions', 'errors'] }), 3000)));
onBeforeUnmount(() => clearInterval(timer));

function downloadCsv() {
    const rows = [['meter', 'customer', 'amount', 'status', 'token', 'units', 'reference'], ...props.transactions.map((t) => [t.target, t.customer_name, t.amount / 100, t.status, t.token || '', t.units || '', t.reference])];
    const blob = new Blob([rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n')], { type: 'text/csv' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `haske-batch-${props.batch.id}.csv` });
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>

<template>
    <Head :title="`Batch #${batch.id}`" />
    <AppLayout :title="`Batch #${batch.id}`">
        <template #actions>
            <button class="btn btn-sm btn-ghost" :disabled="!transactions.length" @click="downloadCsv">Download tokens (CSV)</button>
            <Link :href="route('agent.bulk')" class="btn btn-sm">New batch</Link>
        </template>
        <div class="grid gap-4">
            <div class="card grid gap-2 p-5">
                <div class="flex justify-between text-[14px]"><span>{{ service?.name }} · {{ batch.rows_done }} of {{ batch.rows_total }} processed</span><span class="chip" :class="batch.status === 'done' ? 'chip-success' : 'chip-pending'">{{ batch.status }}</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-accent transition-all" :style="{ width: (batch.rows_done / batch.rows_total) * 100 + '%' }"></div></div>
            </div>
            <div v-if="errors.length" class="rounded-[14px] bg-bad-soft p-4 text-[13.5px] text-bad">
                <b>{{ errors.length }} row(s) not sent</b>
                <ul class="mt-1 mb-0 pl-5"><li v-for="e in errors" :key="e.target"><span class="mono">{{ e.target }}</span>: {{ e.error }}</li></ul>
            </div>
            <section class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Meter</th><th>Customer</th><th class="text-right">Amount</th><th>Status</th><th>Token</th><th>Units</th></tr></thead>
                    <tbody>
                        <tr v-for="t in transactions" :key="t.reference">
                            <td class="mono">{{ t.target }}</td><td>{{ t.customer_name }}</td><td class="num text-right">{{ naira(t.amount) }}</td>
                            <td><StatusChip :status="t.status" /></td>
                            <td class="mono whitespace-nowrap select-all">{{ tokenGroups(t.token) || '—' }}</td><td class="whitespace-nowrap">{{ t.units || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
