<script setup>
import Icon from '@/Components/Icon.vue';
import LcdToken from '@/Components/LcdToken.vue';
import StatusChip from '@/Components/StatusChip.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira, when } from '@/lib/format';
import { lang, t } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ tx: Object });
const routeName = { vtpass: 'VTpass', baxi: 'Baxi (backup route)' };
let timer;
const check = () => router.post(route('wallet.requery', props.tx.reference), {}, { preserveScroll: true });
// While pending, refresh quietly; the server-side requery job does the real work.
onMounted(() => { if (props.tx.status === 'pending') timer = setInterval(() => router.reload({ only: ['tx'] }), 5000); });
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head :title="t('receipt')" />
    <AppLayout>
        <div class="mx-auto grid max-w-[520px] gap-5">
            <div class="flex items-center gap-3">
                <Link :href="route('wallet.history')" class="grid h-9 w-9 place-items-center rounded-[10px] border border-line bg-surface" aria-label="Back"><Icon name="back" :size="18" /></Link>
                <h1 class="m-0 text-[24px] font-extrabold">{{ t('receipt') }}</h1>
            </div>
            <div class="panel grid gap-5">
                <div class="grid justify-items-center gap-1.5 text-center">
                    <StatusChip :status="tx.status" />
                    <div class="num text-[34px] font-extrabold tracking-tight text-heading">{{ naira(tx.total) }}</div>
                    <div class="text-[13.5px] text-muted">{{ tx.service_name }} · {{ tx.meter_type ? t(tx.meter_type) : tx.plan || t('airtime') }}</div>
                </div>
                <LcdToken v-if="tx.status === 'success' && tx.token" :token="tx.token" :short="tx.short_name" :units="tx.units" :meter="tx.target" />
                <div v-if="tx.status === 'success' && !tx.token && tx.category === 'electricity'" class="notice notice-info">{{ t('postpaidMsg') }}</div>
                <div v-if="tx.status === 'pending'" class="notice notice-warn grid gap-3">
                    <span>{{ t('pendingMsg') }}</span>
                    <button class="btn btn-sm btn-ghost justify-self-start" @click="check"><Icon name="refresh" :size="16" />{{ t('checkNow') }}</button>
                </div>
                <div v-if="tx.status === 'refunded'" class="notice notice-error"><b>{{ naira(tx.total) }}</b> {{ t('refundMsg') }} {{ tx.failure_reason }}</div>
                <dl class="m-0 grid border-t border-line text-[13.5px]">
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('ref') }}</dt><dd class="mono m-0">{{ tx.reference }}</dd></div>
                    <template v-if="tx.category === 'electricity'">
                        <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('meterNo') }}</dt><dd class="mono m-0">{{ tx.target }}</dd></div>
                        <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('customer') }}</dt><dd class="m-0">{{ tx.customer_name }}</dd></div>
                    </template>
                    <div v-else class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('phone') }}</dt><dd class="mono m-0">{{ tx.target }}</dd></div>
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('amount') }}</dt><dd class="num m-0">{{ naira(tx.amount) }}</dd></div>
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('fee') }}</dt><dd class="num m-0">{{ naira(tx.fee) }}</dd></div>
                    <div v-if="tx.agent_commission" class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">Commission</dt><dd class="num m-0 text-good">+{{ naira(tx.agent_commission) }}</dd></div>
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('route') }}</dt><dd class="m-0">{{ routeName[tx.provider] || tx.provider }}</dd></div>
                    <div v-if="tx.requery_count" class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('attempts') }}</dt><dd class="num m-0">{{ tx.requery_count }}</dd></div>
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('when') }}</dt><dd class="m-0">{{ when(tx.created_at, lang) }}</dd></div>
                </dl>
                <div class="grid grid-cols-2 gap-2.5"><Link :href="route('wallet.history')" class="btn btn-ghost">{{ t('transactions') }}</Link><Link :href="route('wallet.buy')" class="btn">{{ t('backHome') }}</Link></div>
            </div>
        </div>
    </AppLayout>
</template>
