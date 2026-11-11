<script setup>
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, digits, naira } from '@/lib/format';
import { t } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ me: Object, entries: Object, filter: String });
const page = usePage();
const fundAmount = ref('5000');
const copied = ref(false);
const setFilter = (f) => router.get(route('wallet.wallet'), f ? { type: f } : {}, { preserveState: true, preserveScroll: true });
async function copy() {
    try { await navigator.clipboard.writeText(props.me.virtual_account.account_number); copied.value = true; setTimeout(() => (copied.value = false), 1600); } catch {}
}
const simulate = () => router.post(route('wallet.sandbox-fund'), { amount: Number(fundAmount.value) }, { preserveScroll: true });
const label = (e) => e.description || { purchase: 'Payment', refund: 'Refund', funding: 'Bank transfer', commission: 'Agent commission', manual_credit: 'Credit from support', manual_debit: 'Debit by support' }[e.reason] || e.reason;
</script>

<template>
    <Head :title="t('myWallet')" />
    <AppLayout :title="t('myWallet')" :subtitle="t('walletSub')">
        <template #actions><a href="#fund" class="btn"><Icon name="plus" :size="16" />{{ t('fundWallet') }}</a></template>
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(290px,.8fr)]">
            <div class="grid content-start gap-5">
                <div class="balance-card">
                    <span class="lbl">{{ t('availableBalance') }}</span>
                    <strong class="amt">{{ naira(me.balance, { kobo: true }) }}</strong>
                    <div class="foot"><span>Wallet · {{ me.phone }}</span><span>NGN · Nigerian naira</span></div>
                </div>
                <section class="panel">
                    <div class="panel-head">
                        <h2>{{ t('walletActivity') }}</h2>
                        <select class="input !w-auto !py-1.5 !text-[12px]" :value="filter || ''" @change="setFilter($event.target.value)">
                            <option value="">{{ t('allActivity') }}</option><option value="credit">{{ t('credits') }}</option><option value="debit">{{ t('debits') }}</option>
                        </select>
                    </div>
                    <div v-for="e in entries.data" :key="e.id" class="activity-row">
                        <span class="activity-icon" :class="e.type === 'credit' ? '!bg-good-soft !text-good' : ''"><Icon :name="e.type === 'credit' ? 'arrow-down' : 'arrow-up'" :size="17" /></span>
                        <span class="min-w-0 flex-1"><strong class="block truncate text-[12.5px] text-heading">{{ label(e) }}</strong><small class="text-[11px] text-muted">{{ dateTime(e.created_at) }} · {{ e.reference }}</small></span>
                        <span class="text-right text-[12.5px] font-extrabold whitespace-nowrap num" :class="e.type === 'credit' ? 'text-good' : 'text-heading'">
                            {{ e.type === 'credit' ? '+' : '−' }}{{ naira(e.amount) }}
                            <small class="block text-[10.5px] font-semibold text-muted">bal {{ naira(e.balance_after) }}</small>
                        </span>
                    </div>
                    <p v-if="!entries.data.length" class="py-8 text-center text-[13px] text-muted">No wallet activity.</p>
                    <div class="mt-4"><Pagination :links="entries.links" /></div>
                </section>
            </div>
            <div id="fund" class="grid content-start gap-4">
                <section class="panel">
                    <div class="panel-head"><h2>{{ t('fundTransfer') }}</h2></div>
                    <p class="mt-0 text-[13px] leading-relaxed text-muted">{{ t('vaSub') }}</p>
                    <span class="label mb-[7px]">{{ t('vaTitle') }}</span>
                    <div class="rounded-[10px] border border-dashed border-[#B9C9D9] bg-surface-2 p-3.5">
                        <template v-if="me.virtual_account">
                            <strong class="num block text-[20px] tracking-wider text-heading">{{ me.virtual_account.account_number }}</strong>
                            <div class="hint">{{ me.virtual_account.bank }} · {{ me.virtual_account.account_name }}</div>
                            <button class="btn btn-sm btn-ghost mt-3" @click="copy"><Icon name="copy" :size="14" />{{ copied ? 'Copied ✓' : 'Copy number' }}</button>
                        </template>
                        <span v-else class="hint">Your account number is being set up. Add your name in Profile if you haven't.</span>
                    </div>
                    <template v-if="page.props.sandbox">
                        <label class="label mt-4 mb-[7px]" for="fund-amt">Amount to add (₦)</label>
                        <input id="fund-amt" :value="fundAmount" class="input input-big" inputmode="numeric" @input="fundAmount = digits($event.target.value, 7); $event.target.value = fundAmount" />
                        <button class="btn mt-3 w-full" :disabled="!(Number(fundAmount) >= 100)" @click="simulate">Simulate transfer received</button>
                    </template>
                </section>
                <div class="notice notice-info">Transfers are matched to your account automatically, usually within seconds. Each bank transfer is credited exactly once.</div>
            </div>
        </div>
    </AppLayout>
</template>
