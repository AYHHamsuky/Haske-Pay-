<script setup>
import BuyForm from '@/Components/BuyForm.vue';
import ConfirmPayment from '@/Components/ConfirmPayment.vue';
import Icon from '@/Components/Icon.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { t } from '@/lib/i18n';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ services: Object, category: String });
const review = ref(null);
const busy = ref(false);
const error = ref('');

function pay() {
    busy.value = true;
    error.value = '';
    router.post(route('checkout.store'), review.value.spec, {
        onError: (errs) => { error.value = Object.values(errs)[0]; review.value = null; },
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <Head :title="t('payBill')" />
    <PublicLayout :links="false">
        <div class="bg-bg">
            <div class="mx-auto max-w-[1200px] px-3.5 py-[22px] md:px-7 md:py-[30px]">
                <div class="heading">
                    <div><h1>{{ t('payBill') }}</h1><p>{{ t('paySub') }} No app or account needed.</p></div>
                    <span v-if="$page.props.sandbox" class="demo-pill">SIMULATED PROVIDER</span>
                </div>
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(290px,.8fr)]">
                    <div class="panel"><BuyForm :services="services" :category="category" mode="checkout" :error="error" @submit="(r) => (review = r)" /></div>
                    <div class="grid content-start gap-4">
                        <div class="panel">
                            <div class="panel-head"><h2>{{ t('payFlow') }}</h2></div>
                            <div v-for="([h, s], i) in [[t('flow1'), t('flow1s')], ['Pay with Paystack', 'Card, bank transfer or USSD'], ['Get your token', 'On screen and by SMS']]" :key="h" class="activity-row">
                                <span class="activity-icon font-bold">{{ ['①', '②', '③'][i] }}</span>
                                <span><strong class="block text-[12.5px] text-heading">{{ h }}</strong><small class="text-[11px] text-muted">{{ s }}</small></span>
                            </div>
                        </div>
                        <div class="notice notice-info flex gap-2"><Icon name="lock" :size="16" class="mt-0.5 flex-none" />{{ t('secured') }}. If a token fails, the money is kept safely in a wallet under your phone number.</div>
                    </div>
                </div>
            </div>
        </div>
        <ConfirmPayment v-if="review" :rows="review.rows" :amount="review.amount" :fee="review.fee" :busy="busy" :needs-pin="false" confirm-label="Continue to payment" @confirm="pay" @close="review = null" />
    </PublicLayout>
</template>
