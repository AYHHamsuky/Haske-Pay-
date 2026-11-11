<script setup>
import Logo from '@/Components/Logo.vue';
import Flash from '@/Components/Flash.vue';
import { digits } from '@/lib/format';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({ phone: String });
const page = usePage();
const phoneForm = useForm({ phone: props.phone || '' });
const codeForm = useForm({ code: '' });
const step = computed(() => (props.phone ? 'code' : 'phone'));
const codeInput = ref(null);

watch(step, async (s) => { if (s === 'code') { await nextTick(); codeInput.value?.focus(); } }, { immediate: true });

const send = () => phoneForm.post(route('login.send'), { preserveScroll: true });
const verify = () => codeForm.post(route('login.verify'));
watch(() => codeForm.code, (c) => { if (c.length === 6 && !codeForm.processing) verify(); });
</script>

<template>
    <Head title="Log in" />
    <div class="grid min-h-dvh place-items-center bg-[linear-gradient(115deg,#0d2748_0%,#123b60_62%,#11644e_100%)] px-4 py-10">
        <div class="grid w-full max-w-[400px] gap-6">
            <Link href="/" class="justify-self-center"><Logo light /></Link>
            <div class="grid gap-5 rounded-2xl bg-surface p-6 shadow-[0_30px_80px_#061a2e65]">
                <div>
                    <h1 class="m-0 text-[23px] font-extrabold">Log in</h1>
                    <p class="mt-1 mb-0 text-[14px] text-muted">For customers, agents and staff. We text you a code; no password needed.</p>
                </div>

                <form v-if="step === 'phone'" class="grid gap-4" @submit.prevent="send">
                    <label class="grid gap-1.5"><span class="label">Phone number</span>
                        <input v-model="phoneForm.phone" class="input input-big num" inputmode="tel" autocomplete="tel" placeholder="0803 123 4567" :aria-invalid="!!phoneForm.errors.phone" autofocus />
                    </label>
                    <p v-if="phoneForm.errors.phone" class="-mt-2 text-[13px] text-bad">{{ phoneForm.errors.phone }}</p>
                    <button class="btn" :disabled="phoneForm.processing || digits(phoneForm.phone).length < 10">Send code</button>
                </form>

                <form v-else class="grid gap-4" @submit.prevent="verify">
                    <p class="m-0 text-[14px]">Code sent to <b class="mono">{{ phone }}</b>. <Link :href="route('login', { change: 1 })" class="font-semibold text-accent">Change</Link></p>
                    <label class="grid gap-1.5"><span class="label">6-digit code</span>
                        <input ref="codeInput" :value="codeForm.code" class="input input-big num text-center tracking-[.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" :aria-invalid="!!codeForm.errors.code" @input="codeForm.code = digits($event.target.value, 6); $event.target.value = codeForm.code" />
                    </label>
                    <p v-if="codeForm.errors.code" class="-mt-2 text-[13px] text-bad">{{ codeForm.errors.code }}</p>
                    <p v-if="page.props.flash.dev_code" class="-mt-1 notice notice-warn">Sandbox code: <b class="mono">{{ page.props.flash.dev_code }}</b></p>
                    <button class="btn" :disabled="codeForm.processing || codeForm.code.length !== 6">Log in</button>
                    <button type="button" class="text-[13px] font-semibold text-accent" :disabled="phoneForm.processing" @click="send">Send a new code</button>
                </form>
            </div>
            <p v-if="page.props.sandbox" class="text-center text-[12.5px] text-[#C5D6E7]">Sandbox accounts: customer 08031234567 · agent 08061234567 · admin 08010000001. PIN 1234.</p>
        </div>
        <Flash />
    </div>
</template>
