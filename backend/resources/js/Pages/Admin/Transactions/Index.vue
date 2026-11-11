<script setup>
import Pagination from '@/Components/Pagination.vue';
import StatusChip from '@/Components/StatusChip.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ transactions: Object, filters: Object });
const f = reactive({ q: '', status: '', category: '', provider: '', channel: '', escalated: '', ...props.filters });
let timer;
const apply = () => router.get(route('admin.transactions'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true });
const search = () => { clearTimeout(timer); timer = setTimeout(apply, 300); };
</script>

<template>
    <Head title="Transactions" />
    <AdminLayout title="Transactions">
        <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input v-model="f.q" class="input lg:col-span-2" placeholder="Reference, meter, phone, request id" @input="search" />
            <select v-model="f.status" class="input" @change="apply"><option value="">Any status</option><option v-for="s in ['success', 'pending', 'refunded', 'failed']" :key="s">{{ s }}</option></select>
            <select v-model="f.category" class="input" @change="apply"><option value="">Any category</option><option v-for="s in ['electricity', 'airtime', 'data']" :key="s">{{ s }}</option></select>
            <select v-model="f.provider" class="input" @change="apply"><option value="">Any route</option><option>vtpass</option><option>baxi</option></select>
            <select v-model="f.channel" class="input" @change="apply"><option value="">Any channel</option><option v-for="s in ['app', 'web', 'agent', 'bulk']" :key="s">{{ s }}</option></select>
            <label class="flex items-center gap-2 text-[13.5px] font-semibold lg:col-span-6"><input v-model="f.escalated" type="checkbox" true-value="1" false-value="" @change="apply" />Only escalated (pending over 30 min)</label>
        </div>
        <section class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th><th>Reference</th><th>User</th><th>Service</th><th>Target</th><th class="text-right">Total</th><th>Status</th><th>Route</th><th class="text-right">Margin</th></tr></thead>
                <tbody>
                    <tr v-for="t in transactions.data" :key="t.reference" class="cursor-pointer" @click="router.visit(route('admin.transactions.show', t.id))">
                        <td class="whitespace-nowrap text-muted">{{ dateTime(t.created_at) }}</td>
                        <td><Link :href="route('admin.transactions.show', t.id)" class="mono font-semibold text-accent" @click.stop>{{ t.reference }}</Link></td>
                        <td class="whitespace-nowrap">{{ t.user?.name || t.user?.phone }}<span v-if="t.user?.role === 'agent'" class="chip chip-success ml-1">agent</span></td>
                        <td class="whitespace-nowrap">{{ t.short_name }}<span v-if="t.plan" class="text-muted"> · {{ t.plan }}</span></td>
                        <td class="mono">{{ t.target }}</td>
                        <td class="num text-right font-semibold">{{ naira(t.total) }}</td>
                        <td class="whitespace-nowrap"><StatusChip :status="t.status" /><span v-if="t.escalated" class="chip chip-failed ml-1">escalated</span></td>
                        <td>{{ t.provider }}</td>
                        <td class="num text-right" :class="t.margin ? 'text-good' : 'text-muted'">{{ naira(t.margin) }}</td>
                    </tr>
                    <tr v-if="!transactions.data.length"><td colspan="9" class="py-10 text-center text-muted">No transactions match.</td></tr>
                </tbody>
            </table>
        </section>
        <div class="mt-4"><Pagination :links="transactions.links" /></div>
    </AdminLayout>
</template>
