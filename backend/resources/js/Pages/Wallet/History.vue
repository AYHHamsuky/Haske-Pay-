<script setup>
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusChip from '@/Components/StatusChip.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { t } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ transactions: Object, filters: Object });
const f = reactive({ q: '', status: '', ...props.filters });
let timer;
const apply = () => router.get(route('wallet.history'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true });
const search = () => { clearTimeout(timer); timer = setTimeout(apply, 300); };
const kind = (x) => (x.category === 'electricity' ? t('elec') : x.category === 'data' ? t('data') : t('airtime'));
</script>

<template>
    <Head :title="t('transactions')" />
    <AppLayout :title="t('transactions')" :subtitle="t('txSub')">
        <template #actions><a :href="route('wallet.history.csv', filters)" class="btn btn-ghost"><Icon name="download" :size="16" />{{ t('exportCsv') }}</a></template>
        <section class="panel">
            <div class="panel-head flex-wrap">
                <h2>{{ t('allTx') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <input v-model="f.q" class="input !w-[200px] !py-2 !text-[12.5px]" :placeholder="t('searchRef')" @input="search" />
                    <select v-model="f.status" class="input !w-auto !py-2 !text-[12.5px]" @change="apply">
                        <option value="">{{ t('allStatuses') }}</option>
                        <option v-for="s in ['success', 'pending', 'refunded']" :key="s" :value="s">{{ t(s) }}</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ t('ref') }}</th><th>Service</th><th>Provider</th><th>Recipient</th><th>{{ t('amount') }}</th><th>Status</th><th>{{ t('when') }}</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="x in transactions.data" :key="x.reference">
                            <td><strong class="text-heading">{{ x.reference }}</strong></td>
                            <td>{{ kind(x) }}</td>
                            <td>{{ x.service_name }}<span v-if="x.plan" class="text-muted"> · {{ x.plan }}</span></td>
                            <td class="mono">{{ x.target }}</td>
                            <td><strong class="num">{{ naira(x.total) }}</strong></td>
                            <td><StatusChip :status="x.status" /></td>
                            <td class="text-muted">{{ dateTime(x.created_at) }}</td>
                            <td><Link :href="route('wallet.receipt', x.reference)" class="btn btn-sm btn-ghost">{{ t('detailsBtn') }}</Link></td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!transactions.data.length" class="py-9 text-center text-[13px] text-muted">{{ filters.q || filters.status ? 'No transactions match your filters.' : t('noTx') }}</p>
            </div>
            <div class="mt-4"><Pagination :links="transactions.links" /></div>
        </section>
    </AppLayout>
</template>
