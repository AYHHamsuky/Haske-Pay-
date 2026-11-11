<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const msg = ref(null);
let timer;
watch(() => page.props.flash, (f) => {
    const m = f?.success || f?.error;
    if (!m) return;
    msg.value = { text: m, bad: !!f.error };
    clearTimeout(timer);
    timer = setTimeout(() => (msg.value = null), 4000);
}, { immediate: true, deep: true });
</script>

<template>
    <Transition enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0" enter-active-class="transition" leave-active-class="transition">
        <div v-if="msg" role="status" class="fixed right-5 bottom-5 left-5 z-[60] ml-auto max-w-md rounded-[10px] px-[17px] py-[13px] text-[13px] font-medium text-white shadow-[0_8px_30px_#102b4e30] sm:left-auto" :class="msg.bad ? 'bg-[#B82E2E]' : 'bg-[#102B4E]'">
            {{ msg.text }}
        </div>
    </Transition>
</template>
