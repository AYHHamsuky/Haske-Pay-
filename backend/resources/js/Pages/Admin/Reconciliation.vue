<script setup>
import Stat from '@/Components/Stat.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ runs: Array, selected: Object });
const yesterday = new Date(Date.now() - 864e5).toISOString().slice(0, 10);
const form = useForm({ for_date: yesterday, provider: 'vtpass', statement: null });
const submit = () => form.post(route('admin.reconciliation.store'), { forceFormData: true });
const clean = (r) => r && !r.missing_ours && !r.missing_theirs && !r.mismatched && r.difference === 0;
</script>

<template>
    <Head title="Reconciliation" />
    <AdminLayout title="Reconciliation">
        <div class="grid items-start gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
            <div class="grid gap-5">
                <form class="card grid gap-3 p-5" @submit.prevent="submit">
                    <h2 class="m-0 text-[16px]">Match a statement</h2>
                    <p class="m-0 text-[13px] text-muted">Export the day's transactions from the VTpass dashboard as CSV. We match on request_id, amount and status.</p>
                    <label class="grid gap-1.5"><span class="label">Statement day</span><input v-model="form.for_date" type="date" class="input" :max="new Date().toISOString().slice(0, 10)" /></label>
                    <label class="grid gap-1.5"><span class="label">Provider</span><select v-model="form.provider" class="input"><option>vtpass</option><option>baxi</option></select></label>
                    <label class="grid gap-1.5"><span class="label">CSV file</span><input type="file" accept=".csv,text/csv" class="text-[13px]" @input="form.statement = $event.target.files[0]" /></label>
                    <p v-for="e in Object.values(form.errors)" :key="e" class="m-0 text-[13px] text-bad">{{ e }}</p>
                    <button class="btn" :disabled="!form.statement || form.processing">Reconcile</button>
                    <p class="m-0 text-[12px] text-muted">Columns needed: <span class="mono">request_id, amount, status</span>. From the terminal: <span class="mono">php artisan haske:reconcile 2026-10-27 file.csv</span></p>
                </form>
                <section class="card p-5">
                    <h2 class="m-0 mb-2 text-[16px]">Past runs</h2>
                    <Link v-for="r in runs" :key="r.id" :href="route('admin.reconciliation', { id: r.id })" class="flex items-center justify-between border-b border-line py-2.5 text-[13.5px] last:border-0" :class="{ 'font-bold': selected?.id === r.id }">
                        <span>{{ r.for_date.slice(0, 10) }} · {{ r.provider }}</span>
                        <span class="chip" :class="clean(r) ? 'chip-success' : 'chip-failed'">{{ clean(r) ? 'to the naira' : naira(r.difference) }}</span>
                    </Link>
                    <p v-if="!runs.length" class="m-0 text-[13px] text-muted">No runs yet.</p>
                </section>
            </div>

            <div v-if="selected" class="grid gap-5">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Matched" :value="selected.matched" tone="good" :note="selected.for_date.slice(0, 10)" />
                    <Stat label="Mismatched" :value="selected.mismatched" :tone="selected.mismatched ? 'bad' : 'good'" note="amount or status differ" />
                    <Stat label="Missing" :value="selected.missing_ours + selected.missing_theirs" :tone="selected.missing_ours + selected.missing_theirs ? 'bad' : 'good'" :note="`${selected.missing_ours} not ours · ${selected.missing_theirs} not theirs`" />
                    <Stat label="Difference" :value="naira(selected.difference)" :tone="selected.difference ? 'bad' : 'good'" note="theirs minus ours" />
                </div>
                <section class="card overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Issue</th><th>Request ID</th><th class="text-right">Ours</th><th class="text-right">Theirs</th><th>What to do</th></tr></thead>
                        <tbody>
                            <tr v-for="d in selected.details" :key="d.request_id">
                                <td><span class="chip chip-failed">{{ d.type.replace('_', ' ') }}</span></td>
                                <td class="mono">{{ d.request_id }}<span v-if="d.reference" class="block text-muted">{{ d.reference }}</span></td>
                                <td class="num text-right">{{ d.our_amount != null ? naira(d.our_amount) : '—' }}<span v-if="d.our_status" class="block text-[12px] text-muted">{{ d.our_status }}</span></td>
                                <td class="num text-right">{{ d.their_amount != null ? naira(d.their_amount) : '—' }}<span v-if="d.their_status" class="block text-[12px] text-muted">{{ d.their_status }}</span></td>
                                <td class="text-[13px]">{{ d.note }}</td>
                            </tr>
                            <tr v-if="!selected.details?.length"><td colspan="5" class="py-10 text-center text-good">Statement matches to the naira. ✓</td></tr>
                        </tbody>
                    </table>
                </section>
                <p class="m-0 text-[12px] text-muted">Run {{ dateTime(selected.created_at) }}</p>
            </div>
            <div v-else class="card p-10 text-center text-muted">Upload yesterday's statement to start.</div>
        </div>
    </AdminLayout>
</template>
