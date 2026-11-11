<script setup>
import BuyForm from '@/Components/BuyForm.vue';
import ConfirmPayment from '@/Components/ConfirmPayment.vue';
import Processing from '@/Components/Processing.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira } from '@/lib/format';
import { t } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ services: Object, savedMeters: Array, balance: Number, category: String, idempotencyKey: String });
const page = usePage();
const review = ref(null);
const pinError = ref('');
const formError = ref('');
const processing = ref(false);

function open(r) {
    formError.value = '';
    if (r.amount + r.fee > props.balance) {
        formError.value = `Not enough in your wallet. Add ${naira(r.amount + r.fee - props.balance)} more.`;
        return;
    }
    pinError.value = '';
    review.value = r;
}

function confirm(pin) {
    processing.value = true;
    router.post(route('wallet.purchase'), { ...review.value.spec, pin, idempotency_key: props.idempotencyKey }, {
        onError: (errs) => {
            processing.value = false;
            if (errs.pin) { pinError.value = errs.pin; return; }
            review.value = null;
            formError.value = Object.values(errs)[0];
        },
    });
}
</script>

<template>
    <Head :title="t('payBill')" />
    <AppLayout :title="t('payBill')" :subtitle="t('paySub')">
        <template v-if="$page.props.sandbox" #actions><span class="demo-pill">SIMULATED PROVIDER</span></template>
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(290px,.8fr)]">
            <div class="panel">
                <div v-if="!page.props.auth.has_pin" class="notice notice-warn mb-5">Set a transaction PIN before paying. <Link :href="route('wallet.profile')" class="font-bold underline">Set PIN</Link></div>
                <BuyForm :services="services" :saved-meters="savedMeters" :category="category" :is-agent="page.props.auth.role === 'agent'" :error="formError" :busy="processing" @submit="open" />
            </div>
            <div class="grid content-start gap-4">
                <div class="balance-card">
                    <span class="lbl">AVAILABLE WALLET BALANCE</span>
                    <strong class="amt">{{ naira(balance, { kobo: true }) }}</strong>
                    <div class="foot"><span>Wallet payment</span><Link :href="route('wallet.wallet')">{{ t('addMoney') }}</Link></div>
                </div>
                <div class="panel">
                    <div class="panel-head !mb-1"><h2>{{ t('payFlow') }}</h2></div>
                    <div v-for="([h, s], i) in [[t('flow1'), t('flow1s')], [t('flow2'), t('flow2s')], [t('flow3'), t('flow3s')]]" :key="h" class="activity-row">
                        <span class="activity-icon font-bold">{{ ['①', '②', '③'][i] }}</span>
                        <span><strong class="block text-[12.5px] text-heading">{{ h }}</strong><small class="text-[11px] text-muted">{{ s }}</small></span>
                    </div>
                    <div class="notice notice-warn mt-3">If the provider is slow, your payment shows as pending and we keep checking. It is only refunded once the provider confirms it failed.</div>
                </div>
            </div>
        </div>
        <ConfirmPayment v-if="review && !processing" :rows="review.rows" :amount="review.amount" :fee="review.fee" :error="pinError" @confirm="confirm" @close="review = null" />
        <Processing v-if="processing" :amount="review?.amount + review?.fee" :title="review?.label" :target="review?.target" />
    </AppLayout>
</template>
