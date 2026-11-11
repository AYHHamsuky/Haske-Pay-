<script setup>
import StatusChip from '@/Components/StatusChip.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';

defineProps({ tickets: Array, types: Object, recent: Array });
const page = usePage();
const form = useForm({ reference: '', type: 'token_not_received', message: '' });
const submit = () => form.post(route('wallet.help.store'), { preserveScroll: true, onSuccess: () => form.reset('reference', 'message') });
const faq = [
    ['What if a payment is pending?', 'We keep checking with the provider until it gives a final answer. Your money stays reserved, and most pending payments resolve within a few minutes.'],
    ['When is a failed payment refunded?', 'As soon as the provider confirms it failed, the full amount goes back to your wallet automatically. You will see a refund entry in My wallet.'],
    ['I paid but did not get a token SMS.', 'Open the receipt from Transactions: the token is shown there and can be copied. If the receipt says Successful but your meter rejects it, report it below.'],
    ['How do I add money?', 'Transfer from any bank app to your account number in My wallet. It is credited automatically, usually within seconds.'],
];
</script>

<template>
    <Head :title="t('help')" />
    <AppLayout :title="t('help')" :subtitle="t('helpSub')">
        <div class="grid items-start gap-5 lg:grid-cols-2">
            <div class="grid content-start gap-5">
                <form class="panel" @submit.prevent="submit">
                    <div class="panel-head"><h2>{{ t('reportIssue') }}</h2></div>
                    <div class="mb-[15px]">
                        <label class="label mb-[7px]" for="h-ref">{{ t('ref') }}</label>
                        <input id="h-ref" v-model="form.reference" class="input" list="recent-refs" placeholder="e.g. HP-7K2Q9M4X" />
                        <datalist id="recent-refs"><option v-for="r in recent" :key="r.reference" :value="r.reference">{{ r.label }}</option></datalist>
                    </div>
                    <div class="mb-[15px]">
                        <label class="label mb-[7px]" for="h-type">{{ t('issueType') }}</label>
                        <select id="h-type" v-model="form.type" class="input"><option v-for="(l, k) in types" :key="k" :value="k">{{ l }}</option></select>
                    </div>
                    <div class="mb-[15px]">
                        <label class="label mb-[7px]" for="h-msg">{{ t('describe') }}</label>
                        <textarea id="h-msg" v-model="form.message" rows="4" class="input" placeholder="Tell us what happened"></textarea>
                    </div>
                    <div v-for="e in Object.values(form.errors)" :key="e" class="notice notice-error mb-[15px]">{{ e }}</div>
                    <button class="btn" :disabled="form.processing || form.message.length < 10">{{ t('submit') }}</button>
                    <p class="hint mt-3 mb-0">Urgent? WhatsApp us on <b class="text-heading">{{ page.props.support }}</b> with your reference.</p>
                </form>
                <section v-if="tickets.length" class="panel">
                    <div class="panel-head"><h2>Your requests</h2></div>
                    <div v-for="k in tickets" :key="k.id" class="activity-row">
                        <span class="flex-1"><strong class="block text-[12.5px] text-heading">SUP-{{ k.id }} · {{ types[k.type] }}</strong><small class="text-[11px] text-muted">{{ dateTime(k.created_at) }}{{ k.reference ? ' · ' + k.reference : '' }}</small>
                            <small v-if="k.reply" class="mt-1 block text-[12px] text-ink">Support: {{ k.reply }}</small></span>
                        <span class="chip" :class="k.status === 'resolved' ? 'chip-success' : 'chip-pending'">{{ k.status }}</span>
                    </div>
                </section>
            </div>
            <section class="panel">
                <div class="panel-head"><h2>{{ t('faq') }}</h2></div>
                <template v-for="([q, a], i) in faq" :key="q">
                    <details class="group" :open="i === 0">
                        <summary class="cursor-pointer text-[13.5px] font-semibold text-heading">{{ q }}</summary>
                        <p class="hint mt-2 mb-0 text-[12.5px] leading-relaxed">{{ a }}</p>
                    </details>
                    <hr v-if="i < faq.length - 1" class="my-[15px] border-0 border-t border-line" />
                </template>
            </section>
        </div>
    </AppLayout>
</template>
