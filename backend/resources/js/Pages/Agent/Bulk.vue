<script setup>
import ConfirmPayment from '@/Components/ConfirmPayment.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, naira, toKobo } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ discos: Array, batches: Array, balance: Number });
const form = useForm({ service_code: props.discos.find((d) => d.code === 'kaduna-electric')?.code || props.discos[0]?.code, meter_type: 'prepaid', rows: '', pin: '' });
const asking = ref(false);
const pinError = ref('');

const parsed = computed(() => form.rows.split(/\r?\n/).map((l) => l.trim()).filter(Boolean).map((l) => {
    const [meter = '', amount = '', phone = ''] = l.split(',').map((s) => s.trim());
    const ok = /^(\d{11}|\d{13})$/.test(meter.replace(/\D/g, '')) && toKobo(amount) >= 100000;
    return { meter, amount: toKobo(amount), phone, ok };
}));
const total = computed(() => parsed.value.reduce((s, r) => s + r.amount, 0));
const bad = computed(() => parsed.value.filter((r) => !r.ok).length);

function loadFile(e) {
    const f = e.target.files?.[0];
    if (!f) return;
    f.text().then((txt) => (form.rows = txt.split(/\r?\n/).filter((l) => !/meter/i.test(l)).join('\n')));
}
function run(pin) {
    form.pin = pin;
    form.post(route('agent.bulk.store'), {
        onError: (e) => { if (e.pin) pinError.value = e.pin; else asking.value = false; },
        onSuccess: () => (asking.value = false),
    });
}
</script>

<template>
    <Head title="Bulk vending" />
    <AppLayout title="Bulk vending">
        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <form class="panel grid gap-4" @submit.prevent="asking = true; pinError = ''">
                <p class="m-0 text-[13.5px] text-muted">Vend many meters in one go, for an estate, a landlord or a shop. One meter per line: <span class="mono text-ink">meter, amount, customer phone</span>. The phone is optional; when given, the token is texted to it.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1.5"><span class="label">Distribution company</span>
                        <select v-model="form.service_code" class="input"><option v-for="d in discos" :key="d.code" :value="d.code">{{ d.name }} ({{ d.short_name }})</option></select>
                    </label>
                    <div class="grid gap-1.5"><span class="label">Meter type</span>
                        <div class="seg"><button type="button" :aria-pressed="form.meter_type === 'prepaid'" @click="form.meter_type = 'prepaid'">Prepaid</button><button type="button" :aria-pressed="form.meter_type === 'postpaid'" @click="form.meter_type = 'postpaid'">Postpaid</button></div>
                    </div>
                </div>
                <label class="grid gap-1.5"><span class="label">Meters</span>
                    <textarea v-model="form.rows" rows="9" class="input mono text-[13px] leading-relaxed" placeholder="04223456781, 5000, 08031234567&#10;04229876543, 10000&#10;45012345678, 3000, 08061112222"></textarea>
                </label>
                <label class="text-[13px] font-semibold text-accent">Or upload a CSV <input type="file" accept=".csv,text/csv" class="ml-2 text-[12.5px] text-muted" @change="loadFile" /></label>
                <p v-if="form.errors.rows" class="m-0 text-[13px] text-bad">{{ form.errors.rows }}</p>
                <div v-if="parsed.length" class="num grid gap-1 rounded-[14px] border border-dashed border-line px-4 py-3 text-[13.5px]">
                    <div class="flex justify-between"><span class="text-muted">Meters</span><span>{{ parsed.length }}<span v-if="bad" class="text-bad"> · {{ bad }} need fixing</span></span></div>
                    <div class="flex justify-between"><span class="text-muted">Service fee</span><span>Free (agent)</span></div>
                    <div class="flex justify-between border-t border-line pt-1.5 font-bold"><span>Total</span><span :class="{ 'text-bad': total > balance }">{{ naira(total) }}</span></div>
                </div>
                <button class="btn" :disabled="!parsed.length || bad > 0 || total > balance || form.processing">Vend {{ parsed.length || '' }} meters</button>
            </form>

            <aside class="grid gap-4">
                <div class="stat"><div class="stat-top">Available float</div><strong>{{ naira(balance) }}</strong><small>Agent wallet balance</small></div>
                <section class="panel">
                    <h2 class="m-0 mb-2 text-[16px]">Recent batches</h2>
                    <Link v-for="b in batches" :key="b.id" :href="route('agent.batch', b.id)" class="flex items-center justify-between border-b border-line py-2.5 text-[13.5px] last:border-0">
                        <span><b>#{{ b.id }}</b> · {{ b.service_code }}<br /><span class="text-muted">{{ dateTime(b.created_at) }}</span></span>
                        <span class="num">{{ b.rows_done }}/{{ b.rows_total }} <span class="chip" :class="b.status === 'done' ? 'chip-success' : 'chip-pending'">{{ b.status }}</span></span>
                    </Link>
                    <p v-if="!batches.length" class="m-0 text-[13px] text-muted">No batches yet.</p>
                </section>
            </aside>
        </div>
        <ConfirmPayment v-if="asking" title="Confirm bulk vend" :rows="[['Company', discos.find((d) => d.code === form.service_code)?.name], ['Meter type', form.meter_type], ['Meters', String(parsed.length)]]" :amount="total" :fee="0" :error="pinError" :busy="form.processing" @confirm="run" @close="asking = false" />
    </AppLayout>
</template>
