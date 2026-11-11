<script setup>
import LcdToken from '@/Components/LcdToken.vue';
import StatusChip from '@/Components/StatusChip.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { naira, when } from '@/lib/format';
import { lang, t } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ order: Object });
const state = ref({ status: props.order.status, transaction: props.order.transaction });
const tx = computed(() => state.value.transaction);
const final = computed(() => tx.value && ['success', 'refunded', 'failed'].includes(tx.value.status));
let timer;

async function poll() {
    try {
        state.value = (await axios.get(route('checkout.poll', props.order.reference))).data;
    } catch {}
    if (!final.value) timer = setTimeout(poll, state.value.status === 'awaiting_payment' ? 4000 : 2500);
}
onMounted(() => { if (!final.value) timer = setTimeout(poll, 1500); });
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Head :title="t('receipt')" />
    <PublicLayout :links="false">
        <div class="bg-bg px-3.5 py-10"><div class="mx-auto grid max-w-[480px] gap-5">
            <div class="panel grid gap-5">
                <template v-if="state.status === 'awaiting_payment'">
                    <div class="grid justify-items-center gap-3 py-6 text-center">
                        <div class="spinner"></div>
                        <h2 class="m-0 text-[21px]">{{ lang === 'ha' ? 'Muna jiran tabbacin biya' : 'Waiting for your payment' }}</h2>
                        <p class="m-0 max-w-[32ch] text-muted">{{ lang === 'ha' ? 'Idan ka riga ka biya, wannan shafin zai sabunta da kansa.' : 'If you have already paid, this page updates on its own in a few seconds.' }}</p>
                    </div>
                </template>
                <template v-else-if="!tx">
                    <div class="grid justify-items-center gap-3 py-6 text-center">
                        <div class="spinner"></div>
                        <h2 class="m-0 text-[21px]">{{ t('processing') }} {{ naira(order.amount + order.fee) }}</h2>
                        <p class="m-0 text-muted">{{ order.service }} · <span class="mono">{{ order.target }}</span></p>
                    </div>
                </template>
                <template v-else>
                    <div class="grid justify-items-center gap-1.5 text-center">
                        <StatusChip :status="tx.status" />
                        <div class="num text-[34px] font-extrabold tracking-tight text-heading">{{ naira(tx.total) }}</div>
                        <div class="text-[13.5px] text-muted">{{ order.service }} · {{ tx.meter_type ? t(tx.meter_type) : tx.plan || t('airtime') }}</div>
                    </div>
                    <LcdToken v-if="tx.status === 'success' && tx.token" :token="tx.token" :short="order.short_name" :units="tx.units" :meter="tx.target" />
                    <div v-if="tx.status === 'success' && !tx.token && tx.category === 'electricity'" class="notice notice-info">{{ t('postpaidMsg') }}</div>
                    <div v-if="tx.status === 'pending'" class="notice notice-warn">{{ t('pendingMsg') }}</div>
                    <div v-if="tx.status === 'refunded'" class="notice notice-error">
                        <b>{{ naira(tx.total) }}</b> {{ lang === 'ha' ? 'yana cikin walat ɗin lambar wayarka. Shiga da lambar ka sake gwadawa, ko ka tuntuɓi tallafi don a mayar maka.' : 'is in the Haske wallet for your phone number. Log in with that number to try again, or contact support for a bank refund.' }}
                    </div>
                </template>

                <dl class="m-0 grid border-t border-line text-[13.5px]">
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('ref') }}</dt><dd class="mono m-0">{{ tx?.reference || order.reference }}</dd></div>
                    <div v-if="order.customer_name" class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('customer') }}</dt><dd class="m-0">{{ order.customer_name }}</dd></div>
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ order.category === 'electricity' ? t('meterNo') : t('phone') }}</dt><dd class="mono m-0">{{ order.target }}</dd></div>
                    <div class="flex justify-between gap-3 border-b border-line py-2.5"><dt class="text-muted">{{ t('when') }}</dt><dd class="m-0">{{ when(order.created_at, lang) }}</dd></div>
                </dl>
                <Link href="/" class="btn btn-ghost">{{ t('backHome') }}</Link>
            </div>
            <p class="text-center text-[12.5px] text-muted">{{ lang === 'ha' ? 'Kana da matsala? Tuntuɓe mu ta WhatsApp' : 'Problem with this order? WhatsApp us on' }} {{ $page.props.support }} {{ lang === 'ha' ? 'da lambar shaida.' : 'with the reference.' }}</p>
        </div></div>
    </PublicLayout>
</template>
