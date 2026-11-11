<script setup>
import BarChart from '@/Components/BarChart.vue';
import Stat from '@/Components/Stat.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ stats: Object });
const s = props.stats;
const ledgerOk = s.ledger_check?.ok;
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout title="Today">
        <div class="grid gap-5">
            <div v-if="s.escalated_count" class="flex flex-wrap items-center justify-between gap-3 rounded-[16px] bg-bad-soft px-5 py-4 text-bad">
                <span><b>{{ s.escalated_count }} purchase(s) pending over 30 minutes.</b> Check with the provider, then resolve each one.</span>
                <Link :href="route('admin.transactions', { escalated: 1 })" class="btn btn-sm btn-danger">Resolve now</Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Sales today" :value="naira(s.today.volume)" :note="`${s.today.delivered} delivered`" />
                <Stat label="Margin today" :value="naira(s.today.margin)" note="fees + commission − agent share" tone="good" />
                <Stat label="Failure rate" :value="s.today.failure_rate + '%'" :note="`${s.today.failed} refunded`" :tone="s.today.failure_rate > 5 ? 'bad' : 'good'" />
                <Stat label="Pending now" :value="s.pending_count" :note="s.oldest_pending_minutes !== null ? `oldest ${s.oldest_pending_minutes} min` : 'queue empty'" :tone="s.escalated_count ? 'bad' : s.pending_count ? 'warn' : 'good'" />
            </div>

            <section class="card p-5">
                <div class="mb-3 flex items-baseline justify-between"><h2 class="m-0 text-[16px]">Purchases by day</h2><span class="text-[12.5px] text-muted">last {{ s.series.length }} days</span></div>
                <BarChart :rows="s.series" :series="[{ key: 'delivered', label: 'Delivered', color: 'var(--accent)' }, { key: 'pending', label: 'Pending', color: 'var(--warn)' }, { key: 'failed', label: 'Refunded', color: 'var(--bad)' }]" />
            </section>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="card p-5">
                    <h2 class="m-0 mb-3 text-[16px]">Margin by day</h2>
                    <BarChart :rows="s.series" :series="[{ key: 'margin', label: 'Margin', color: 'var(--good)' }]" :format="naira" :height="150" />
                </section>
                <section class="card grid content-start gap-3 p-5">
                    <h2 class="m-0 text-[16px]">System health</h2>
                    <div class="flex items-start justify-between gap-3 border-b border-line pb-3 text-[13.5px]">
                        <span><b class="block">Ledger check</b><span class="text-muted">{{ s.ledger_check ? 'Last run ' + dateTime(s.ledger_check.created_at) : 'Not run yet (nightly at 01:00)' }}</span></span>
                        <span class="chip" :class="ledgerOk ? 'chip-success' : s.ledger_check ? 'chip-failed' : 'chip-pending'">{{ ledgerOk ? 'Balanced' : s.ledger_check ? 'Mismatch' : '—' }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-3 border-b border-line pb-3 text-[13.5px]">
                        <span><b class="block">Provider float</b>
                            <span v-if="s.provider_balance" class="text-muted">VTpass {{ s.provider_balance.data.vtpass != null ? naira(s.provider_balance.data.vtpass) : '?' }} · Baxi {{ s.provider_balance.data.baxi != null ? naira(s.provider_balance.data.baxi) : '?' }}</span>
                            <span v-else class="text-muted">Checked every 15 minutes</span></span>
                        <span class="chip" :class="s.provider_balance?.ok === false ? 'chip-failed' : 'chip-success'">{{ s.provider_balance?.ok === false ? 'Low' : 'OK' }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-3 border-b border-line pb-3 text-[13.5px]">
                        <span><b class="block">Customer wallets held</b><span class="text-muted">sum of all balances</span></span><b class="num">{{ naira(s.wallets_total) }}</b>
                    </div>
                    <div class="text-[13.5px]"><b class="block">Services switched off</b><span class="text-muted">{{ s.services_off.length ? s.services_off.join(', ') : 'None' }}</span></div>
                </section>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="card overflow-x-auto">
                    <table class="table"><thead><tr><th>Today by category</th><th class="text-right">Sales</th><th class="text-right">Value</th><th class="text-right">Margin</th></tr></thead>
                        <tbody>
                            <tr v-for="c in s.by_category" :key="c.category"><td class="capitalize">{{ c.category }}</td><td class="num text-right">{{ c.n }}</td><td class="num text-right">{{ naira(c.volume) }}</td><td class="num text-right text-good">{{ naira(c.margin) }}</td></tr>
                            <tr v-if="!s.by_category.length"><td colspan="4" class="py-6 text-center text-muted">No sales yet today.</td></tr>
                        </tbody></table>
                </section>
                <section class="card overflow-x-auto">
                    <table class="table"><thead><tr><th>Failures, last 24h</th><th>Route</th><th class="text-right">Count</th></tr></thead>
                        <tbody>
                            <tr v-for="f in s.top_failures" :key="f.service_code + f.provider"><td>{{ f.service_code }}</td><td>{{ f.provider }}</td><td class="num text-right text-bad">{{ f.n }}</td></tr>
                            <tr v-if="!s.top_failures.length"><td colspan="3" class="py-6 text-center text-muted">No failures. </td></tr>
                        </tbody></table>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
