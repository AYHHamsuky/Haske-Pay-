<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { naira } from '@/lib/format';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ services: Array });
const editing = ref(null);
const draft = ref({});
const groups = ['electricity', 'airtime', 'data'];

function start(s) {
    editing.value = s.id;
    draft.value = { active: s.active, provider: s.provider, fallback_provider: s.fallback_provider, commission_rate: s.commission_rate, fee: s.fee / 100, min_amount: s.min_amount / 100, max_amount: s.max_amount / 100 };
}
function payload(s, d) {
    return { ...d, fee: Math.round(d.fee * 100), min_amount: Math.round(d.min_amount * 100), max_amount: Math.round(d.max_amount * 100), fallback_provider: d.fallback_provider || null };
}
function save(s) {
    router.patch(route('admin.services.update', s.id), payload(s, draft.value), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
function toggle(s) {
    const d = { active: !s.active, provider: s.provider, fallback_provider: s.fallback_provider, commission_rate: s.commission_rate, fee: s.fee / 100, min_amount: s.min_amount / 100, max_amount: s.max_amount / 100 };
    router.patch(route('admin.services.update', s.id), payload(s, d), { preserveScroll: true });
}
</script>

<template>
    <Head title="Services" />
    <AdminLayout title="Services">
        <p class="-mt-3 mb-5 max-w-[70ch] text-[13.5px] text-muted">Switch a DisCo off when its route is failing; customers see it as unavailable instead of losing time to refunds. Update commission rates from the VTpass rate card.</p>
        <div class="grid gap-5">
            <section v-for="g in groups" :key="g" class="card overflow-x-auto">
                <h2 class="m-0 px-5 pt-4 text-[16px] capitalize">{{ g }}</h2>
                <table class="table">
                    <thead><tr><th>On</th><th>Service</th><th>Route</th><th class="text-right">Commission</th><th class="text-right">Fee</th><th class="text-right">Limits</th><th class="text-right">24h</th><th></th></tr></thead>
                    <tbody>
                        <template v-for="s in services.filter((x) => x.category === g)" :key="s.id">
                            <tr v-if="editing !== s.id">
                                <td><button class="relative h-6 w-10 rounded-full transition" :class="s.active ? 'bg-accent' : 'bg-line'" role="switch" :aria-checked="s.active" :aria-label="'Toggle ' + s.name" @click="toggle(s)"><span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-surface shadow transition" :class="{ 'translate-x-4': s.active }"></span></button></td>
                                <td><b>{{ s.name }}</b> <span class="text-muted">{{ s.short_name }}</span><span class="mono block text-[11px] text-muted">{{ s.code }}</span></td>
                                <td>{{ s.provider }}<span v-if="s.fallback_provider" class="text-muted"> → {{ s.fallback_provider }}</span></td>
                                <td class="num text-right">{{ s.commission_rate }}%</td>
                                <td class="num text-right">{{ naira(s.fee) }}</td>
                                <td class="num text-right whitespace-nowrap text-muted">{{ naira(s.min_amount) }}–{{ naira(s.max_amount) }}</td>
                                <td class="num text-right">{{ s.last_24h }}<span v-if="s.success_rate !== null" :class="s.success_rate < 90 ? 'text-bad' : 'text-good'"> · {{ s.success_rate }}%</span></td>
                                <td class="text-right"><button class="text-[13px] font-semibold text-accent" @click="start(s)">Edit</button></td>
                            </tr>
                            <tr v-else class="bg-surface-2">
                                <td><input v-model="draft.active" type="checkbox" /></td>
                                <td><b>{{ s.name }}</b></td>
                                <td class="whitespace-nowrap"><select v-model="draft.provider" class="input w-24 py-1.5 text-[13px]"><option>vtpass</option><option>baxi</option></select> → <select v-model="draft.fallback_provider" class="input w-24 py-1.5 text-[13px]"><option :value="null">none</option><option>vtpass</option><option>baxi</option></select></td>
                                <td><input v-model.number="draft.commission_rate" class="input w-20 py-1.5 text-right text-[13px]" type="number" step="0.1" /></td>
                                <td><input v-model.number="draft.fee" class="input w-20 py-1.5 text-right text-[13px]" type="number" /></td>
                                <td class="whitespace-nowrap"><input v-model.number="draft.min_amount" class="input w-24 py-1.5 text-right text-[13px]" type="number" /> – <input v-model.number="draft.max_amount" class="input w-28 py-1.5 text-right text-[13px]" type="number" /></td>
                                <td></td>
                                <td class="text-right whitespace-nowrap"><button class="btn btn-sm" @click="save(s)">Save</button> <button class="btn btn-sm btn-ghost" @click="editing = null">Cancel</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </section>
        </div>
    </AdminLayout>
</template>
