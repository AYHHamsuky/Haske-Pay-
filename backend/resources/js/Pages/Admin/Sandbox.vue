<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ outcome: String, outcomes: Array, log: Array, lastFunding: Object });
const help = {
    auto: '85% instant success, 10% pending then success, 5% failed and refunded.',
    success: 'The provider returns a token straight away.',
    pending: 'Provider says "processing". The requery job resolves it on the second check.',
    failed: 'Provider rejects it. The wallet is refunded automatically and the ledger shows both entries.',
    down: 'VTpass is unreachable. The same request_id goes to the Baxi backup route; no double debit.',
};
const set = (o) => router.post(route('admin.sandbox.outcome'), { outcome: o }, { preserveScroll: true });
const run = (command) => router.post(route('admin.sandbox.run'), { command }, { preserveScroll: true });
const replay = () => router.post(route('admin.sandbox.replay'), {}, { preserveScroll: true });
let timer;
onMounted(() => (timer = setInterval(() => router.reload({ only: ['log'] }), 3000)));
onBeforeUnmount(() => clearInterval(timer));
const tone = { ok: 'text-good', warn: 'text-warn', bad: 'text-bad' };
</script>

<template>
    <Head title="Sandbox" />
    <AdminLayout title="Behind the screen">
        <p class="-mt-3 mb-5 max-w-[70ch] text-[13.5px] text-muted">Sandbox mode runs VTpass, Baxi, Paystack and Termii in-process. Pick what the provider does next, then buy from the app or the web checkout and watch the ledger here.</p>
        <div class="grid items-start gap-5 lg:grid-cols-[380px_minmax(0,1fr)]">
            <div class="grid gap-5">
                <section class="card grid gap-3 p-5">
                    <span class="eyebrow">Next purchase outcome</span>
                    <div class="flex flex-wrap gap-1.5"><button v-for="o in outcomes" :key="o" class="pill capitalize" :aria-pressed="outcome === o" @click="set(o)">{{ o === 'down' ? 'VTpass down' : o === 'auto' ? 'Realistic mix' : o }}</button></div>
                    <p class="m-0 text-[13px] text-muted">{{ help[outcome] }}</p>
                </section>
                <section class="card grid gap-2 p-5">
                    <span class="eyebrow">Tests</span>
                    <button class="btn btn-sm btn-ghost justify-start" :disabled="!lastFunding" @click="replay">Replay last bank webhook</button>
                    <button class="btn btn-sm btn-ghost justify-start" @click="run('haske:requery')">Run requery job now</button>
                    <button class="btn btn-sm btn-ghost justify-start" @click="run('haske:audit-ledger')">Run ledger audit</button>
                    <button class="btn btn-sm btn-ghost justify-start" @click="run('haske:provider-balance')">Check provider float</button>
                    <p class="m-0 text-[12px] text-muted">The same checks run on the scheduler: requery every 20 s, float every 15 min, ledger nightly.</p>
                </section>
            </div>
            <section class="card grid gap-2 p-5">
                <span class="eyebrow">Live system log</span>
                <ol class="mono m-0 grid max-h-[620px] list-none gap-1.5 overflow-auto rounded-xl border border-line bg-surface-2 p-3 text-[11.5px] leading-relaxed">
                    <li v-for="(l, i) in log" :key="i" class="grid grid-cols-[64px_72px_minmax(0,1fr)] gap-2 max-sm:grid-cols-[64px_minmax(0,1fr)]">
                        <time class="text-muted">{{ new Date(l.at).toLocaleTimeString('en-GB') }}</time>
                        <span class="font-semibold max-sm:hidden" :class="tone[l.level] || 'text-accent'">{{ l.tag }}</span>
                        <span class="break-words" :class="l.level === 'bad' ? 'text-bad' : l.level === 'warn' ? 'text-warn' : ''">{{ l.msg }}</span>
                    </li>
                    <li v-if="!log.length" class="text-muted">Nothing yet. Make a purchase.</li>
                </ol>
            </section>
        </div>
    </AdminLayout>
</template>
