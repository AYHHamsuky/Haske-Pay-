<script setup>
import LcdToken from '@/Components/LcdToken.vue';
import Modal from '@/Components/Modal.vue';
import StatusChip from '@/Components/StatusChip.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ tx: Object, entries: Array, audit: Array });
const resolving = ref(null);
const form = useForm({ outcome: 'delivered', token: '', reason: '' });
const open = (o) => { form.reset(); form.outcome = o; resolving.value = o; };
const resolve = () => form.post(route('admin.transactions.resolve', props.tx.id), { preserveScroll: true, onSuccess: () => (resolving.value = null) });
const requery = () => router.post(route('admin.transactions.requery', props.tx.id), {}, { preserveScroll: true });
const rows = (x) => [
    ['Reference', x.reference, true], ['Request ID (sent to provider)', x.request_id, true], ['Provider ref', x.provider_ref || '—', true],
    ['Service', `${x.service_name} (${x.service_code})`], ['Target', x.target, true], ['Customer', x.customer_name || '—'],
    ['SMS to', x.customer_phone || x.user.phone, true], ['Channel', x.channel], ['Route', x.provider],
    ['Face value', naira(x.amount)], ['Fee', naira(x.fee)], ['Provider commission', naira(x.commission)], ['Agent commission', naira(x.agent_commission)], ['Our margin', naira(x.margin)],
    ['Status checks', x.requery_count], ['Next check', dateTime(x.next_requery_at)], ['Escalated', dateTime(x.escalated_at)],
    ['Created', dateTime(x.created_at)], ['Completed', dateTime(x.completed_at)], ['Failure reason', x.failure_reason || '—'],
];
</script>

<template>
    <Head :title="tx.reference" />
    <AdminLayout :title="tx.reference">
        <template #actions>
            <template v-if="tx.status === 'pending'">
                <button class="btn btn-sm btn-ghost" @click="requery">Requery now</button>
                <button class="btn btn-sm" @click="open('delivered')">Mark delivered</button>
                <button class="btn btn-sm btn-danger" @click="open('refund')">Refund</button>
            </template>
        </template>
        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
            <div class="grid gap-5">
                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-3 border-b border-line p-5">
                        <StatusChip :status="tx.status" /><b class="num font-display text-[26px]">{{ naira(tx.total) }}</b>
                        <span class="text-muted">by <Link :href="route('admin.users.show', tx.user.id)" class="font-semibold text-accent">{{ tx.user.name || tx.user.phone }}</Link></span>
                    </div>
                    <dl class="m-0 grid sm:grid-cols-2">
                        <div v-for="[k, v, mono] in rows(tx)" :key="k" class="flex justify-between gap-3 border-b border-line px-5 py-2.5 text-[13.5px] sm:odd:border-r">
                            <dt class="text-muted">{{ k }}</dt><dd class="m-0 text-right break-all" :class="{ mono }">{{ v }}</dd>
                        </div>
                    </dl>
                </section>
                <section class="card overflow-x-auto">
                    <h2 class="m-0 px-5 pt-4 text-[16px]">Ledger entries</h2>
                    <table class="table"><thead><tr><th>When</th><th>Reference</th><th>Reason</th><th class="text-right">Amount</th><th class="text-right">Balance after</th></tr></thead>
                        <tbody><tr v-for="e in entries" :key="e.id"><td class="text-muted">{{ dateTime(e.created_at) }}</td><td class="mono">{{ e.reference }}</td><td>{{ e.reason }}</td><td class="num text-right font-semibold" :class="{ 'text-good': e.type === 'credit' }">{{ e.type === 'credit' ? '+' : '−' }}{{ naira(e.amount) }}</td><td class="num text-right">{{ naira(e.balance_after) }}</td></tr></tbody>
                    </table>
                </section>
                <section class="card grid gap-3 p-5">
                    <h2 class="m-0 text-[16px]">Provider payloads</h2>
                    <details><summary class="cursor-pointer text-[13.5px] font-semibold">Request</summary><pre class="mono mt-2 overflow-x-auto rounded-xl bg-surface-2 p-3 text-[11.5px]">{{ JSON.stringify(tx.request_payload, null, 2) }}</pre></details>
                    <details open><summary class="cursor-pointer text-[13.5px] font-semibold">Last response</summary><pre class="mono mt-2 overflow-x-auto rounded-xl bg-surface-2 p-3 text-[11.5px]">{{ JSON.stringify(tx.response_payload, null, 2) }}</pre></details>
                </section>
            </div>
            <aside class="grid gap-4">
                <div v-if="tx.token" class="card p-4"><LcdToken :token="tx.token" :short="tx.short_name" :units="tx.units" :meter="tx.target" /></div>
                <section class="card p-5">
                    <h2 class="m-0 mb-2 text-[16px]">Admin actions</h2>
                    <div v-for="a in audit" :key="a.id" class="border-b border-line py-2 text-[13px] last:border-0"><b>{{ a.action }}</b> by {{ a.actor?.name || a.actor?.phone }}<br /><span class="text-muted">{{ dateTime(a.created_at) }} · {{ a.data?.reason }}</span></div>
                    <p v-if="!audit.length" class="m-0 text-[13px] text-muted">None.</p>
                </section>
            </aside>
        </div>

        <Modal v-if="resolving" :title="resolving === 'delivered' ? 'Mark as delivered' : 'Refund to wallet'" @close="resolving = null">
            <p class="m-0 text-[13.5px] text-muted">{{ resolving === 'delivered' ? 'Use this only after the provider confirms it vended. The customer is not refunded.' : 'Use this only after the provider confirms it did not vend. The full amount goes back to the wallet.' }}</p>
            <label v-if="resolving === 'delivered' && tx.category === 'electricity'" class="grid gap-1.5"><span class="label">Token from provider</span><input v-model="form.token" class="input input-big num" placeholder="20 digits" /></label>
            <label class="grid gap-1.5"><span class="label">Reason (kept in the audit log)</span><input v-model="form.reason" class="input" placeholder="e.g. Confirmed by VTpass support ticket #4821" /></label>
            <p v-if="form.errors.reason" class="-mt-2 text-[13px] text-bad">{{ form.errors.reason }}</p>
            <button class="btn" :class="{ 'btn-danger': resolving === 'refund' }" :disabled="form.processing || form.reason.length < 5" @click="resolve">{{ resolving === 'delivered' ? 'Mark delivered' : 'Refund ' + naira(tx.total) }}</button>
        </Modal>
    </AdminLayout>
</template>
