<script setup>
import Icon from '@/Components/Icon.vue';
import Stat from '@/Components/Stat.vue';
import TxRow from '@/Components/TxRow.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira } from '@/lib/format';
import { greeting, lang, t } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ me: Object, recent: Array, stats: Object, agent: Object });
const first = computed(() => props.me.name?.split(' ')[0]);
const limitLeft = computed(() => Math.max(0, props.me.daily_limit - props.me.spent_today));
const services = [['electricity', 'bolt', '', 'elec', 'elecSub'], ['airtime', 'phone', 'orange', 'airtime', 'airSub'], ['data', 'wifi', 'blue', 'data', 'dataSub']];
const quick = [['electricity', 'bolt', '', 'payElec', 'Prepaid & postpaid', 'pay2'], ['airtime', 'phone', 'orange', 'buyAir', 'MTN, Airtel, Glo, 9mobile', 'buy2'], ['data', 'wifi', 'blue', 'buyData', 'Bundles for your network', 'buy2']];
</script>

<template>
    <Head :title="t('overview')" />
    <AppLayout :title="`${greeting()}${first ? ', ' + first : ''} 👋`" :subtitle="t('overviewSub')">
        <template #actions><Link :href="route('wallet.buy')" class="btn"><Icon name="plus" :size="16" />{{ t('makePayment') }}</Link></template>

        <div v-if="!me.has_pin" class="notice notice-warn mb-5 flex flex-wrap items-center justify-between gap-3">
            <span><b>Set your transaction PIN.</b> You need a 4-digit PIN to pay from your wallet.</span>
            <Link :href="route('wallet.profile')" class="btn btn-sm">Set PIN</Link>
        </div>

        <div class="mb-[22px] grid grid-cols-2 gap-2.5 md:gap-4 xl:grid-cols-4">
            <Stat :label="t('balance')" :value="naira(me.balance, { kobo: true })" :note="t('available')" icon="wallet" />
            <Stat :label="t('successful')" :value="stats.successful" :note="t('acrossServices')" icon="check" />
            <Stat :label="t('totalSpent')" :value="naira(stats.spent)" :note="t('successfulTx')" icon="arrow-up-right" />
            <Stat v-if="agent" :label="t('todayComm')" :value="naira(agent.today.commission)" :note="t('feeWaived')" icon="chart" tone="good" />
            <Stat v-else :label="t('savedCount')" :value="stats.saved_meters" :note="t('quickRepeat')" icon="bolt" />
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(290px,.8fr)]">
            <div class="grid content-start gap-5">
                <section class="panel">
                    <div class="panel-head"><h2>{{ t('whatPay') }}</h2><small>{{ t('chooseService') }}</small></div>
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <Link v-for="[k, i, cls, l, s] in services" :key="k" :href="route('wallet.buy', { c: k })" class="service-card !p-3 sm:!px-[13px] sm:!py-[17px]">
                            <span class="icon-tile" :class="cls"><Icon :name="i" :size="22" /></span>
                            <strong>{{ t(l) }}</strong><small class="hidden sm:block">{{ t(s) }}</small>
                        </Link>
                    </div>
                </section>
                <section class="panel">
                    <div class="panel-head"><h2>{{ t('recentTx') }}</h2><Link :href="route('wallet.history')" class="btn btn-sm btn-ghost">{{ t('viewAll') }}</Link></div>
                    <div v-if="recent.length"><TxRow v-for="x in recent" :key="x.reference" :tx="x" :href="route('wallet.receipt', x.reference)" /></div>
                    <p v-else class="py-8 text-center text-[13px] text-muted">{{ t('noTx') }}</p>
                </section>
            </div>

            <div class="grid content-start gap-4">
                <div class="balance-card">
                    <span class="lbl">WALLET BALANCE</span>
                    <strong class="amt">{{ naira(me.balance, { kobo: true }) }}</strong>
                    <div class="foot">
                        <span>{{ me.virtual_account ? `${me.virtual_account.bank} · ${me.virtual_account.account_number}` : 'Account number being set up…' }}</span>
                        <Link :href="route('wallet.wallet')">＋ {{ t('addMoney') }}</Link>
                    </div>
                </div>
                <section class="panel !pb-2">
                    <div class="panel-head !mb-1"><h2>{{ t('quickActions') }}</h2></div>
                    <div v-for="[k, i, cls, l, s, b] in quick" :key="k" class="activity-row">
                        <span class="icon-tile !mb-0 !h-[38px] !w-[38px] !rounded-[11px]" :class="cls"><Icon :name="i" :size="18" /></span>
                        <span class="flex-1"><strong class="block text-[12.5px] text-heading">{{ t(l) }}</strong><small class="text-[11px] text-muted">{{ s }}</small></span>
                        <Link :href="route('wallet.buy', { c: k })" class="btn btn-sm btn-ghost">{{ t(b) }}</Link>
                    </div>
                </section>
                <div class="panel">
                    <div class="panel-head !mb-3"><h2>{{ lang === 'ha' ? 'Iyakar yau' : 'Daily limit' }}</h2><small>{{ naira(me.daily_limit) }}</small></div>
                    <div class="h-2 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-accent" :style="{ width: Math.min(100, (me.spent_today / me.daily_limit) * 100) + '%' }"></div></div>
                    <p class="hint mt-2 mb-0"><b class="num text-heading">{{ naira(limitLeft) }}</b> {{ lang === 'ha' ? 'ya rage yau' : 'left today' }}</p>
                </div>
                <div v-if="$page.props.sandbox" class="notice notice-info">🔒 <strong>Sandbox:</strong> no real money is moved and no bill is actually paid.</div>
            </div>
        </div>
    </AppLayout>
</template>
