<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { naira } from '@/lib/format';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ order: Object });
const busy = ref(false);
const pay = () => { busy.value = true; router.post(route('checkout.sandbox-complete', props.order.reference), {}, { onFinish: () => (busy.value = false) }); };
</script>

<template>
    <Head title="Sandbox payment" />
    <PublicLayout :links="false">
        <div class="bg-bg px-3.5 py-10"><div class="mx-auto grid max-w-[420px] gap-4">
            <div class="notice notice-warn"><b>Sandbox.</b> This page stands in for Paystack checkout. With live keys, buyers go to Paystack here.</div>
            <div class="panel grid gap-4">
                <span class="eyebrow">Pay Haske Pay</span>
                <div class="num text-[38px] font-extrabold text-heading">{{ naira(order.total) }}</div>
                <p class="m-0 text-muted">{{ order.service }} · <span class="mono">{{ order.target }}</span></p>
                <button class="btn" :disabled="busy || order.status !== 'awaiting_payment'" @click="pay">{{ order.status === 'awaiting_payment' ? 'Simulate successful payment' : 'Already paid' }}</button>
                <a :href="route('checkout.show', order.reference)" class="btn btn-ghost">Cancel</a>
            </div>
        </div></div>
    </PublicLayout>
</template>
