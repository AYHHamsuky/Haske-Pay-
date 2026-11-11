<script setup>
import Icon from '@/Components/Icon.vue';
import { detectNetwork, digits, naira, normPhone, phoneOk, toKobo } from '@/lib/format';
import { lang, t } from '@/lib/i18n';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

/**
 * Step 1 (service) and step 2 (details) of "Pay a bill". Emits submit(payload) for
 * the parent's review step. The server recomputes fees and data prices regardless.
 */
const props = defineProps({
    services: { type: Object, required: true }, // { electricity: [...], airtime: [...], data: [...] }
    savedMeters: { type: Array, default: () => [] },
    mode: { type: String, default: 'wallet' }, // wallet | checkout
    category: { type: String, default: 'electricity' },
    isAgent: Boolean,
    busy: Boolean,
    error: String,
});
const emit = defineEmits(['submit', 'update:category']);

const cat = ref(['electricity', 'airtime', 'data'].includes(props.category) ? props.category : 'electricity');
watch(cat, (c) => emit('update:category', c));

const discos = computed(() => props.services.electricity || []);
const nets = computed(() => props.services.airtime || []);

const blankE = () => ({ code: discos.value.find((d) => d.code === 'kaduna-electric')?.code || discos.value[0]?.code, type: 'prepaid', meter: '', customer: null, verifying: false, meterErr: '', amount: '' });
const E = reactive(blankE());
const P = reactive({ phone: '', net: null, manual: false, amount: '', plan: null, plans: [], loadingPlans: false });
const buyerPhone = ref('');

const disco = computed(() => discos.value.find((d) => d.code === E.code));
const meterOk = computed(() => /^(\d{11}|\d{13})$/.test(E.meter));
const saved = computed(() => props.savedMeters.filter((m) => m.service_code === E.code && m.meter_type === E.type));

watch(() => [E.code, E.type], () => { E.customer = null; E.meterErr = ''; });
watch(() => E.meter, () => { E.customer = null; E.meterErr = ''; });

async function verify() {
    if (!meterOk.value) {
        E.meterErr = lang.value === 'ha' ? 'Lambar mita tana da lamba 11 ko 13.' : 'Meter numbers have 11 or 13 digits.';
        return;
    }
    const want = `${E.code}|${E.type}|${E.meter}`;
    E.verifying = true;
    E.meterErr = '';
    try {
        const { data } = await axios.post(route('web.verify-meter'), { service_code: E.code, meter: E.meter, meter_type: E.type });
        if (want === `${E.code}|${E.type}|${E.meter}`) E.customer = data;
    } catch (e) {
        E.meterErr = e.response?.data?.error || e.response?.data?.message || 'Could not check that meter. Try again.';
    } finally {
        E.verifying = false;
    }
}

function pickSaved(m) {
    E.meter = m.meter_number;
    setTimeout(verify);
}

const phone = computed(() => normPhone(P.phone));
watch(() => P.phone, (v) => {
    if (!P.manual || digits(v).length < 4) {
        const n = detectNetwork(v);
        if (n !== P.net) { P.net = n; P.plan = null; }
        P.manual = false;
    }
});
const netService = computed(() => nets.value.find((n) => n.network === P.net));
const dataService = computed(() => (props.services.data || []).find((n) => n.network === P.net));
watch(() => [cat.value, dataService.value?.code], async () => {
    P.plans = [];
    P.plan = null;
    if (cat.value !== 'data' || !dataService.value) return;
    P.loadingPlans = true;
    try {
        P.plans = (await axios.get(route('web.plans', dataService.value.code))).data;
    } finally {
        P.loadingPlans = false;
    }
}, { immediate: true });
const plan = computed(() => P.plans.find((p) => p.code === P.plan));

const quote = computed(() => {
    if (cat.value === 'electricity') {
        const amount = toKobo(E.amount);
        return { amount, fee: disco.value?.fee ?? 0, min: disco.value?.min_amount ?? 100000, max: disco.value?.max_amount ?? 50000000 };
    }
    if (cat.value === 'airtime') {
        return { amount: toKobo(P.amount), fee: 0, min: netService.value?.min_amount ?? 5000, max: netService.value?.max_amount ?? 5000000 };
    }
    return { amount: plan.value?.amount ?? 0, fee: 0, min: 0, max: Infinity };
});
const amountOk = computed(() => quote.value.amount >= quote.value.min && quote.value.amount <= quote.value.max);
const buyerOk = computed(() => props.mode !== 'checkout' || phoneOk(buyerPhone.value));

const ready = computed(() => {
    if (!buyerOk.value) return false;
    if (cat.value === 'electricity') return !!E.customer && amountOk.value;
    if (cat.value === 'airtime') return phoneOk(P.phone) && !!netService.value?.active && amountOk.value;
    return phoneOk(P.phone) && !!plan.value;
});

function reset() {
    Object.assign(E, blankE());
    Object.assign(P, { phone: '', net: null, manual: false, amount: '', plan: null });
    buyerPhone.value = '';
}
defineExpose({ reset });

function submit() {
    if (!ready.value || props.busy) return;
    const base = props.mode === 'checkout' ? { customer_phone: normPhone(buyerPhone.value) } : {};
    const q = quote.value;
    if (cat.value === 'electricity') {
        emit('submit', {
            spec: { ...base, service_code: E.code, target: E.meter, meter_type: E.type, amount: q.amount },
            amount: q.amount, fee: q.fee, label: disco.value.name, target: E.meter,
            rows: [[t('elec'), `${disco.value.name}`], [t('meterNo'), `${E.meter} · ${t(E.type)}`], [t('customer'), E.customer.name]],
        });
    } else if (cat.value === 'airtime') {
        emit('submit', {
            spec: { ...base, service_code: netService.value.code, target: phone.value, amount: q.amount },
            amount: q.amount, fee: 0, label: `${netService.value.name} airtime`, target: phone.value,
            rows: [[t('airtime'), netService.value.name], [t('phone'), phone.value]],
        });
    } else {
        emit('submit', {
            spec: { ...base, service_code: dataService.value.code, target: phone.value, variation_code: plan.value.code },
            amount: plan.value.amount, fee: 0, label: `${dataService.value.name} ${plan.value.name}`, target: phone.value,
            rows: [[t('data'), dataService.value.name], [t('plan'), plan.value.name], [t('phone'), phone.value]],
        });
    }
}

const cards = [
    ['electricity', 'bolt', '', 'elec', 'elecSub'],
    ['airtime', 'phone', 'orange', 'airtime', 'airSub'],
    ['data', 'wifi', 'blue', 'data', 'dataSub'],
];
</script>

<template>
    <form @submit.prevent="submit">
        <div class="panel-head"><h2>{{ t('step1') }}</h2><small>{{ t('stepOf', { n: 1 }) }}</small></div>
        <div class="mb-6 grid grid-cols-3 gap-2 sm:gap-3" role="radiogroup">
            <button v-for="[k, icon, tone, label, sub] in cards" :key="k" type="button" role="radio" class="service-card !p-3 sm:!px-[13px] sm:!py-[17px]" :aria-pressed="cat === k" :aria-checked="cat === k" @click="cat = k">
                <span class="icon-tile" :class="tone"><Icon :name="icon" :size="22" /></span>
                <strong>{{ t(label) }}</strong>
                <small class="hidden sm:block">{{ t(sub) }}</small>
            </button>
        </div>

        <div class="panel-head"><h2>{{ t('step2') }}</h2><small>{{ t('stepOf', { n: 2 }) }}</small></div>

        <!-- Electricity -->
        <template v-if="cat === 'electricity'">
            <div class="mb-[15px]">
                <label class="label mb-[7px]" for="bf-disco">{{ t('disco') }}</label>
                <select id="bf-disco" v-model="E.code" class="input">
                    <option v-for="d in discos" :key="d.code" :value="d.code" :disabled="!d.active">{{ d.name }} ({{ d.short_name }}){{ d.active ? '' : ' · unavailable' }}</option>
                </select>
            </div>
            <div class="mb-[15px] grid gap-3 sm:grid-cols-2">
                <div>
                    <span class="label mb-[7px]">{{ t('meterType') }}</span>
                    <div class="seg" role="group">
                        <button type="button" :aria-pressed="E.type === 'prepaid'" @click="E.type = 'prepaid'">{{ t('prepaid') }}</button>
                        <button type="button" :aria-pressed="E.type === 'postpaid'" @click="E.type = 'postpaid'">{{ t('postpaid') }}</button>
                    </div>
                </div>
                <div>
                    <label class="label mb-[7px]" for="bf-meter">{{ t('meterNo') }}</label>
                    <input id="bf-meter" :value="E.meter" class="input input-big" inputmode="numeric" autocomplete="off" maxlength="13" placeholder="11 or 13 digits" :aria-invalid="!!E.meterErr"
                        @input="E.meter = digits($event.target.value, 13); $event.target.value = E.meter" @keydown.enter.prevent="verify" />
                </div>
            </div>
            <div v-if="saved.length" class="mb-[15px] flex flex-wrap items-center gap-1.5">
                <span class="hint mr-1">{{ t('saved') }}:</span>
                <button v-for="m in saved" :key="m.id" type="button" class="pill" :aria-pressed="E.meter === m.meter_number" @click="pickSaved(m)">{{ m.customer_name.split(' ')[0] }} · {{ m.meter_number.slice(-4) }}</button>
            </div>
            <div v-if="E.customer" class="notice notice-success mb-[15px]">
                ✓ {{ t('verified') }}<br /><strong>{{ t('customer') }}:</strong> {{ E.customer.name }}<br />
                <span v-if="E.customer.address">{{ E.customer.address }}</span><template v-if="E.customer.band"> · Band {{ E.customer.band }}</template>
            </div>
            <template v-else>
                <button type="button" class="btn btn-ghost" :disabled="!meterOk || E.verifying" @click="verify"><Icon name="check" :size="16" />{{ E.verifying ? t('verifying') : t('verify') }}</button>
                <div v-if="E.meterErr" class="notice notice-error mt-3">{{ E.meterErr }}</div>
                <p v-if="$page.props.vtpassSandbox" class="hint mt-2 mb-[15px]">VTpass sandbox: use <b>1111111111111</b> (prepaid) or <b>1010101010101</b> (postpaid). Other meters fail.</p>
                <p v-else-if="$page.props.billsSandbox" class="hint mt-2 mb-[15px]">Sandbox: any 11 or 13-digit meter works; all zeros returns "not found".</p>
                <div v-else class="mb-[15px]"></div>
            </template>
            <div class="mb-[15px]">
                <label class="label mb-[7px]" for="bf-amount">{{ t('amount') }} (₦)</label>
                <input id="bf-amount" :value="E.amount" class="input input-big" inputmode="numeric" autocomplete="off" :placeholder="'Min ' + naira(quote.min)" @input="E.amount = digits($event.target.value, 6); $event.target.value = E.amount" />
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button v-for="a in [2000, 5000, 10000, 20000]" :key="a" type="button" class="pill" :aria-pressed="Number(E.amount) === a" @click="E.amount = String(a)">{{ naira(a * 100) }}</button>
                </div>
            </div>
        </template>

        <!-- Airtime / data -->
        <template v-else>
            <div class="mb-[15px] grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label mb-[7px]" for="bf-phone">{{ t('phone') }}</label>
                    <input id="bf-phone" :value="P.phone" class="input input-big" inputmode="tel" autocomplete="off" maxlength="13" placeholder="0803 123 4567" @input="P.phone = digits($event.target.value, 13); $event.target.value = P.phone" />
                </div>
                <div>
                    <span class="label mb-[7px]">{{ t('network') }}<span v-if="P.net && !P.manual" class="font-semibold text-accent-text"> · detected</span></span>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="n in nets" :key="n.code" type="button" class="pill" :aria-pressed="P.net === n.network" @click="P.net = n.network; P.manual = true; P.plan = null">{{ n.name }}</button>
                    </div>
                </div>
            </div>
            <p v-if="digits(P.phone).length >= 4 && !P.net" class="hint -mt-2 mb-[15px]">Network not recognised from the number. Pick one.</p>
            <p v-if="$page.props.vtpassSandbox" class="hint -mt-2 mb-[15px]">VTpass sandbox: <b>08011111111</b> succeeds · <b>201000000000</b> pending · <b>300000000000</b> timeout · <b>400000000000</b> no response · any other number fails.</p>
            <div v-if="cat === 'airtime'" class="mb-[15px]">
                <label class="label mb-[7px]" for="bf-air">{{ t('amount') }} (₦)</label>
                <input id="bf-air" :value="P.amount" class="input input-big" inputmode="numeric" autocomplete="off" placeholder="₦50 – ₦50,000" @input="P.amount = digits($event.target.value, 5); $event.target.value = P.amount" />
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button v-for="a in [100, 200, 500, 1000, 2000]" :key="a" type="button" class="pill" :aria-pressed="Number(P.amount) === a" @click="P.amount = String(a)">{{ naira(a * 100) }}</button>
                </div>
            </div>
            <div v-else class="mb-[15px]">
                <span class="label mb-[7px]">{{ t('plan') }}</span>
                <p v-if="!P.net" class="hint m-0">Choose a network first.</p>
                <p v-else-if="P.loadingPlans" class="hint m-0">Loading plans…</p>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <button v-for="p in P.plans" :key="p.code" type="button" class="service-card flex items-center justify-between !p-3" :aria-pressed="P.plan === p.code" @click="P.plan = p.code">
                        <span class="text-[13px] font-bold text-heading">{{ p.name }}</span><b class="num text-[13px]">{{ naira(p.amount) }}</b>
                    </button>
                </div>
            </div>
        </template>

        <div v-if="mode === 'checkout'" class="mb-[15px]">
            <label class="label mb-[7px]" for="bf-buyer">{{ t('yourPhone') }}</label>
            <input id="bf-buyer" :value="buyerPhone" class="input input-big" inputmode="tel" autocomplete="tel" maxlength="13" placeholder="0803 123 4567" @input="buyerPhone = digits($event.target.value, 13); $event.target.value = buyerPhone" />
            <div class="hint mt-1.5">{{ t('yourPhoneHint') }}</div>
        </div>

        <div class="notice notice-info mb-[15px]">
            <template v-if="quote.amount">
                {{ t('fee') }}: <b>{{ quote.fee ? naira(quote.fee) : isAgent ? 'Free (agent)' : naira(0) }}</b> · {{ t('total') }}: <b class="num">{{ naira(quote.amount + quote.fee) }}</b>
            </template>
            <template v-else>{{ cat === 'electricity' && !isAgent ? `A ${naira(disco?.fee || 0)} service fee applies to electricity.` : 'No service fee.' }} The final amount is shown before you confirm.</template>
        </div>
        <div v-if="quote.amount && !amountOk" class="notice notice-error mb-[15px]">Enter between {{ naira(quote.min) }} and {{ naira(quote.max) }}.</div>
        <div v-if="error" class="notice notice-error mb-[15px]" role="alert">{{ error }}</div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="submit" class="btn" :disabled="!ready || busy">{{ t('review') }} <Icon name="arrow-right" :size="16" /></button>
            <button type="button" class="btn btn-ghost" @click="reset">Reset</button>
        </div>
    </form>
</template>
