<script setup>
import Icon from '@/Components/Icon.vue';
import Stat from '@/Components/Stat.vue';
import TxRow from '@/Components/TxRow.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira } from '@/lib/format';
import { t } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ me: Object, report: Object, week: Object, rates: Array, recent: Array });
</script>

<template>
    <Head :title="t('agentDashboard')" />
    <AppLayout :title="t('agentDashboard')" subtitle="Manage your POS business, sales and commissions.">
        <template #actions>
            <Link :href="route('agent.bulk')" class="btn btn-ghost"><Icon name="layers" :size="16" />{{ t('bulkVending') }}</Link>
            <Link :href="route('wallet.buy')" class="btn"><Icon name="plus" :size="16" />Serve a customer</Link>
        </template>

        <div class="mb-5 flex flex-col items-start justify-between gap-3 rounded-[13px] border border-[#CCEEDD] bg-[linear-gradient(120deg,#e8f8ef,#f2fbf6)] px-5 py-[18px] sm:flex-row sm:items-center dark:border-line dark:bg-none dark:bg-accent-soft">
            <div><strong class="mb-1 block text-heading">🏪 {{ me.agent.business_name }}</strong><p class="m-0 text-[12px] text-[#5E7B6E] dark:text-muted">Agent · {{ me.phone }} · Commission tier: <b class="capitalize">{{ me.agent.tier }}</b> (you keep {{ me.agent.share }}%) · Status: Active</p></div>
            <span class="chip chip-success">ACTIVE</span>
        </div>

        <div class="mb-[22px] grid grid-cols-2 gap-2.5 md:gap-4 xl:grid-cols-4">
            <Stat label="Today's sales" :value="naira(report.today.volume)" :note="`Across ${report.today.sales} transactions`" icon="receipt" />
            <Stat label="Commission earned" :value="naira(week.commission)" note="Last 7 days" icon="arrow-up-right" tone="good" />
            <Stat label="Available float" :value="naira(me.balance)" note="Agent wallet balance" icon="wallet" />
            <Stat label="Transactions" :value="report.today.sales" :note="`Today · ${week.sales} this week`" icon="list" />
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(290px,.8fr)]">
            <section class="panel">
                <div class="panel-head"><h2>Today's sales</h2><Link :href="route('wallet.history')" class="btn btn-sm btn-ghost">{{ t('viewAll') }}</Link></div>
                <div v-if="recent.length"><TxRow v-for="x in recent" :key="x.reference" :tx="x" :href="route('wallet.receipt', x.reference)" /></div>
                <p v-else class="py-8 text-center text-[13px] text-muted">No sales yet today. Tap "Serve a customer" to vend.</p>
            </section>
            <div class="grid content-start gap-4">
                <section class="panel">
                    <div class="panel-head !mb-1"><h2>Commission summary</h2><Link :href="route('agent.commission')" class="text-[12px] font-bold text-accent-text">Report →</Link></div>
                    <div v-for="r in rates" :key="r.category" class="activity-row">
                        <span class="flex-1"><strong class="block text-[12.5px] capitalize text-heading">{{ r.category }}</strong><small class="text-[11px] text-muted">{{ r.note }}</small></span>
                        <strong class="num text-heading">{{ r.rate }}</strong>
                    </div>
                </section>
                <div class="notice notice-info">Your customers pay no service fee. Commission is credited to your wallet the moment each token or top-up is delivered.</div>
            </div>
        </div>
    </AppLayout>
</template>
