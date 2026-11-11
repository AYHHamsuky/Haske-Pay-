<script setup>
import Logo from '@/Components/Logo.vue';
import { digits } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { onMounted, ref, watch } from 'vue';

const props = defineProps({ setup: Object });
const form = useForm({ code: '' });
const qr = ref('');
onMounted(async () => { if (props.setup) qr.value = await QRCode.toDataURL(props.setup.otpauth, { margin: 1, width: 220 }); });
const submit = () => form.post(route('2fa.verify'));
watch(() => form.code, (c) => { if (c.length === 6 && !form.processing) submit(); });
</script>

<template>
    <Head title="Two-factor check" />
    <div class="grid min-h-dvh place-items-center bg-[linear-gradient(115deg,#0d2748_0%,#123b60_62%,#11644e_100%)] px-4 py-10">
        <div class="grid w-full max-w-[420px] gap-6">
            <Logo light class="justify-self-center" />
            <form class="grid gap-5 rounded-2xl bg-surface p-6 shadow-[0_30px_80px_#061a2e65]" @submit.prevent="submit">
                <div>
                    <h1 class="m-0 text-[23px] font-extrabold">{{ setup ? 'Set up two-factor' : 'Two-factor check' }}</h1>
                    <p class="mt-1 mb-0 text-[14px] text-muted">{{ setup ? 'Admin pages move money, so they need a second factor. Scan this with Google Authenticator, Authy or 1Password.' : 'Enter the 6-digit code from your authenticator app.' }}</p>
                </div>
                <template v-if="setup">
                    <img v-if="qr" :src="qr" alt="QR code for your authenticator app" class="justify-self-center rounded-xl bg-white p-2" width="220" height="220" />
                    <p class="m-0 text-center text-[12.5px] text-muted">Can't scan? Enter this key: <span class="mono block pt-1 break-all text-ink select-all">{{ setup.secret }}</span></p>
                </template>
                <input :value="form.code" class="input input-big num text-center tracking-[.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" autofocus :aria-invalid="!!form.errors.code" @input="form.code = digits($event.target.value, 6); $event.target.value = form.code" />
                <p v-if="form.errors.code" class="-mt-2 text-[13px] text-bad">{{ form.errors.code }}</p>
                <button class="btn" :disabled="form.processing || form.code.length !== 6">{{ setup ? 'Turn on and continue' : 'Continue' }}</button>
                <button type="button" class="text-[13px] font-semibold text-muted" @click="router.post('/logout')">Log out</button>
            </form>
        </div>
    </div>
</template>
