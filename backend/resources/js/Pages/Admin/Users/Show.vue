<script setup>
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import Stat from '@/Components/Stat.vue';
import TxRow from '@/Components/TxRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ user: Object, wallet: Object, entries: Object, transactions: Array });
const adjusting = ref(null);
const adj = useForm({ type: 'credit', amount: '', reason: '' });
const edit = useForm({
    name: props.user.name, role: props.user.role, status: props.user.status,
    business_name: props.user.agent?.business_name || '', location: props.user.agent?.location || '', commission_tier: props.user.agent?.commission_tier || 'bronze',
});
const openAdjust = (t) => { adj.reset(); adj.type = t; adjusting.value = t; };
const postAdjust = () => adj.post(route('admin.users.adjust', props.user.id), { preserveScroll: true, onSuccess: () => (adjusting.value = null) });
const save = () => edit.patch(route('admin.users.update', props.user.id), { preserveScroll: true });
const unlock = () => router.post(route('admin.users.unlock-pin', props.user.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="user.name || user.phone" />
    <AdminLayout :title="user.name || user.phone">
        <template #actions>
            <button class="btn btn-sm" @click="openAdjust('credit')">Manual credit</button>
            <button class="btn btn-sm btn-ghost" @click="openAdjust('debit')">Manual debit</button>
        </template>
        <div class="grid gap-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <Stat label="Wallet balance" :value="naira(wallet.balance)" :note="wallet.balance === wallet.ledger ? '✓ matches ledger' : '✗ ledger says ' + naira(wallet.ledger)" :tone="wallet.balance === wallet.ledger ? 'good' : 'bad'" />
                <Stat label="Spent today" :value="naira(user.spent_today)" :note="'limit ' + naira(user.daily_limit)" />
                <Stat label="Virtual account" :value="user.virtual_account?.account_number || '—'" :note="user.virtual_account?.bank" />
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
                <div class="grid gap-5">
                    <section class="card overflow-x-auto">
                        <h2 class="m-0 px-5 pt-4 text-[16px]">Ledger</h2>
                        <table class="table"><thead><tr><th>When</th><th>Reference</th><th>Reason</th><th class="text-right">Amount</th><th class="text-right">Balance after</th></tr></thead>
                            <tbody><tr v-for="e in entries.data" :key="e.id"><td class="whitespace-nowrap text-muted">{{ dateTime(e.created_at) }}</td><td class="mono">{{ e.reference }}</td><td>{{ e.reason }}<span v-if="e.description" class="block text-[12px] text-muted">{{ e.description }}</span></td><td class="num text-right font-semibold" :class="{ 'text-good': e.type === 'credit' }">{{ e.type === 'credit' ? '+' : '−' }}{{ naira(e.amount) }}</td><td class="num text-right">{{ naira(e.balance_after) }}</td></tr></tbody>
                        </table>
                        <div class="p-4"><Pagination :links="entries.links" /></div>
                    </section>
                    <section class="card p-5">
                        <h2 class="m-0 mb-2 text-[16px]">Recent purchases</h2>
                        <TxRow v-for="x in transactions" :key="x.reference" :tx="x" :href="route('admin.transactions.show', x.id)" />
                        <p v-if="!transactions.length" class="m-0 text-[13px] text-muted">None.</p>
                    </section>
                </div>

                <form class="card grid gap-3 p-5" @submit.prevent="save">
                    <h2 class="m-0 text-[16px]">Account</h2>
                    <div class="text-[13px] text-muted"><span class="mono text-ink">{{ user.phone }}</span> · joined {{ dateTime(user.created_at) }}<br />PIN {{ user.has_pin ? 'set' : 'not set' }} · device {{ user.device_id || '—' }}<template v-if="user.role === 'admin'"> · 2FA {{ user.two_factor ? 'on' : 'off' }}</template></div>
                    <div v-if="user.pin_locked_until && new Date(user.pin_locked_until) > new Date()" class="flex items-center justify-between gap-2 rounded-xl bg-warn-soft px-3 py-2 text-[13px] text-warn">PIN locked until {{ dateTime(user.pin_locked_until) }}<button type="button" class="font-bold underline" @click="unlock">Unlock</button></div>
                    <label class="grid gap-1.5"><span class="label">Name</span><input v-model="edit.name" class="input" /></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1.5"><span class="label">Role</span><select v-model="edit.role" class="input"><option>customer</option><option>agent</option><option>admin</option></select></label>
                        <label class="grid gap-1.5"><span class="label">Status</span><select v-model="edit.status" class="input"><option>active</option><option>suspended</option></select></label>
                    </div>
                    <template v-if="edit.role === 'agent'">
                        <label class="grid gap-1.5"><span class="label">Business name</span><input v-model="edit.business_name" class="input" /></label>
                        <label class="grid gap-1.5"><span class="label">Location</span><input v-model="edit.location" class="input" /></label>
                        <label class="grid gap-1.5"><span class="label">Commission tier</span><select v-model="edit.commission_tier" class="input"><option value="bronze">Bronze · 50%</option><option value="silver">Silver · 60%</option><option value="gold">Gold · 70%</option></select></label>
                    </template>
                    <p v-for="e in Object.values(edit.errors)" :key="e" class="m-0 text-[13px] text-bad">{{ e }}</p>
                    <button class="btn" :disabled="edit.processing || !edit.isDirty">Save changes</button>
                </form>
            </div>
        </div>

        <Modal v-if="adjusting" :title="adjusting === 'credit' ? 'Manual credit' : 'Manual debit'" @close="adjusting = null">
            <p class="m-0 text-[13.5px] text-muted">Posts a ledger entry under your name. Use it for support refunds and corrections, never for top-ups that should come through Paystack.</p>
            <label class="grid gap-1.5"><span class="label">Amount (₦)</span><input v-model="adj.amount" class="input input-big num" inputmode="decimal" /></label>
            <label class="grid gap-1.5"><span class="label">Reason</span><input v-model="adj.reason" class="input" placeholder="e.g. Refund for HP-7K2Q9M4X, DisCo confirmed no token" /></label>
            <p v-for="e in Object.values(adj.errors)" :key="e" class="m-0 text-[13px] text-bad">{{ e }}</p>
            <button class="btn" :class="{ 'btn-danger': adjusting === 'debit' }" :disabled="adj.processing || !adj.amount || adj.reason.length < 8" @click="postAdjust">{{ adjusting === 'credit' ? 'Credit' : 'Debit' }} {{ adj.amount ? naira(Number(adj.amount) * 100) : '' }}</button>
        </Modal>
    </AdminLayout>
</template>
