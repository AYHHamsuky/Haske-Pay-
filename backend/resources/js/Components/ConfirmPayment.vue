<script setup>
import Modal from '@/Components/Modal.vue';
import { digits, naira } from '@/lib/format';
import { t } from '@/lib/i18n';
import { nextTick, onMounted, ref, watch } from 'vue';

/**
 * The demo's "Confirm payment" step: review rows, total, then the 4-digit PIN.
 * rows: [[label, value]]. Emits confirm(pin); the parent shows errors via `error`.
 */
const props = defineProps({ rows: Array, amount: Number, fee: Number, busy: Boolean, error: String, title: String, needsPin: { type: Boolean, default: true }, confirmLabel: String });
const emit = defineEmits(['confirm', 'close']);
const pin = ref('');
const input = ref(null);
onMounted(() => nextTick(() => input.value?.focus()));
watch(() => props.error, (e) => { if (e) { pin.value = ''; input.value?.focus(); } });
const submit = () => (!props.needsPin || pin.value.length === 4) && !props.busy && emit('confirm', pin.value);
</script>

<template>
    <Modal :title="title || t('confirmPay')" @close="emit('close')">
        <p class="m-0 -mt-2 text-[13px] text-muted">{{ t('confirmSub') }}</p>
        <div class="grid gap-2 rounded-[10px] bg-surface-2 p-4 text-[13px]">
            <div v-for="[k, v] in rows" :key="k" class="flex justify-between gap-4"><b class="font-semibold text-muted">{{ k }}</b><span class="text-right font-semibold break-all text-heading">{{ v }}</span></div>
            <hr class="my-1 border-0 border-t border-line" />
            <div class="flex justify-between"><span class="text-muted">{{ t('amount') }}</span><span class="num">{{ naira(amount) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ t('fee') }}</span><span class="num">{{ naira(fee || 0) }}</span></div>
            <div class="mt-1 flex items-baseline justify-between"><span class="font-bold text-heading">{{ t('total') }}</span><span class="num text-[22px] font-extrabold text-accent-text">{{ naira(amount + (fee || 0)) }}</span></div>
        </div>
        <form @submit.prevent="submit">
            <template v-if="needsPin">
            <label class="label mb-[7px]" for="confirm-pin">{{ t('pinLabel') }}</label>
            <input id="confirm-pin" ref="input" :value="pin" class="input input-big text-center tracking-[.6em]" type="password" inputmode="numeric" autocomplete="off" maxlength="4" placeholder="••••" :aria-invalid="!!error"
                @input="pin = digits($event.target.value, 4); $event.target.value = pin" />
            </template>
            <p v-if="error" class="mt-2 mb-0 text-[13px] font-medium text-bad" role="alert">{{ error }}</p>
            <div class="mt-5 flex justify-end gap-2.5">
                <button type="button" class="btn btn-ghost" @click="emit('close')">{{ t('cancel') }}</button>
                <button type="submit" class="btn" :disabled="(needsPin && pin.length !== 4) || busy">{{ busy ? '…' : confirmLabel || t('confirmPay') }}</button>
            </div>
        </form>
    </Modal>
</template>
