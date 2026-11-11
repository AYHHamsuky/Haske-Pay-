<script setup>
import Icon from '@/Components/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira } from '@/lib/format';
import { setLang, t } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ me: Object, savedMeters: Array, security: Object });
const profile = useForm({ name: props.me.name || '', locale: props.me.locale });
const pin = useForm({ current_pin: '', pin: '', pin_confirmation: '' });
const saveProfile = () => profile.patch(route('wallet.profile.update'), { preserveScroll: true, onSuccess: () => setLang(profile.locale) });
const savePin = () => pin.post(route('wallet.pin'), { preserveScroll: true, onSuccess: () => pin.reset() });
const remove = (id) => router.delete(route('wallet.meters.delete', id), { preserveScroll: true });
const checks = [
    ['check', 'Phone login with SMS code', 'Every sign-in needs a code sent to your phone', true],
    ['lock', 'Transaction PIN', 'Asked for every payment · 3 wrong tries locks it for 30 min', props.me.has_pin],
    ['phone', 'This device only', 'Signing in on a new phone signs out the old one', true],
];
</script>

<template>
    <Head :title="t('profileSecurity')" />
    <AppLayout :title="t('profileSecurity')" :subtitle="t('profileSub')">
        <div class="grid items-start gap-5 lg:grid-cols-2">
            <form class="panel" @submit.prevent="saveProfile">
                <div class="panel-head"><h2>{{ t('personal') }}</h2></div>
                <div class="mb-[15px]"><label class="label mb-[7px]" for="p-name">Full name</label><input id="p-name" v-model="profile.name" class="input" autocomplete="name" /></div>
                <p v-if="profile.errors.name" class="-mt-2 mb-3 text-[13px] text-bad">{{ profile.errors.name }}</p>
                <div class="mb-[15px]"><label class="label mb-[7px]">Phone number</label><input class="input bg-surface-2" :value="me.phone" disabled /></div>
                <div class="mb-[15px]"><label class="label mb-[7px]" for="p-lang">Preferred language</label>
                    <select id="p-lang" v-model="profile.locale" class="input"><option value="en">English</option><option value="ha">Hausa</option></select>
                </div>
                <div v-if="me.agent" class="notice notice-success mb-[15px]">🏪 <b>{{ me.agent.business_name }}</b> · {{ me.agent.tier }} tier · you keep {{ me.agent.share }}% of commission</div>
                <button class="btn" :disabled="profile.processing">Save preferences</button>
            </form>

            <section class="panel">
                <div class="panel-head"><h2>{{ t('securityChecklist') }}</h2><small>Daily limit {{ naira(me.daily_limit) }}</small></div>
                <div v-for="[i, h, s, ok] in checks" :key="h" class="activity-row">
                    <span class="activity-icon"><Icon :name="i" :size="17" /></span>
                    <span class="flex-1"><strong class="block text-[12.5px] text-heading">{{ h }}</strong><small class="text-[11px] text-muted">{{ s }}</small></span>
                    <span class="chip" :class="ok ? 'chip-success' : 'chip-pending'">{{ ok ? 'On' : 'Set it up' }}</span>
                </div>
            </section>

            <form class="panel" @submit.prevent="savePin">
                <div class="panel-head"><h2>{{ me.has_pin ? 'Change transaction PIN' : 'Set transaction PIN' }}</h2></div>
                <div v-if="me.has_pin" class="mb-[15px]"><label class="label mb-[7px]">Current PIN</label><input v-model="pin.current_pin" class="input input-big" type="password" inputmode="numeric" maxlength="4" autocomplete="off" /></div>
                <div class="mb-[15px] grid grid-cols-2 gap-3">
                    <div><label class="label mb-[7px]">New PIN</label><input v-model="pin.pin" class="input input-big" type="password" inputmode="numeric" maxlength="4" autocomplete="off" /></div>
                    <div><label class="label mb-[7px]">Repeat</label><input v-model="pin.pin_confirmation" class="input input-big" type="password" inputmode="numeric" maxlength="4" autocomplete="off" /></div>
                </div>
                <div v-for="e in [pin.errors.pin, pin.errors.current_pin].filter(Boolean)" :key="e" class="notice notice-error mb-[15px]">{{ e }}</div>
                <button class="btn" :disabled="pin.processing || pin.pin.length !== 4">Save PIN</button>
            </form>

            <section class="panel">
                <div class="panel-head"><h2>{{ t('saved') }}</h2><small>{{ savedMeters.length }}</small></div>
                <div v-for="m in savedMeters" :key="m.id" class="activity-row">
                    <span class="icon-tile !mb-0 !h-[38px] !w-[38px] !rounded-[11px]"><Icon name="bolt" :size="18" /></span>
                    <span class="min-w-0 flex-1"><strong class="block text-[12.5px] text-heading">{{ m.customer_name }}</strong><small class="mono text-[11px] text-muted">{{ m.meter_number }} · {{ t(m.meter_type) }} · {{ m.service_code }}</small></span>
                    <button class="btn btn-sm btn-danger" @click="remove(m.id)">Remove</button>
                </div>
                <p v-if="!savedMeters.length" class="m-0 text-[13px] text-muted">Meters you pay for are saved here automatically.</p>
            </section>
        </div>
    </AppLayout>
</template>
