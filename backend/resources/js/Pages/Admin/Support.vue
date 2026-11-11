<script setup>
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ tickets: Object, types: Object, status: String });
const open = ref(null);
const form = useForm({ reply: '', notify: true });
const start = (k) => { form.reset(); open.value = k; };
const send = () => form.patch(route('admin.support.update', open.value.id), { preserveScroll: true, onSuccess: () => (open.value = null) });
const setStatus = (s) => router.get(route('admin.support'), { status: s }, { preserveState: true });
</script>

<template>
    <Head title="Support" />
    <AdminLayout title="Support requests" subtitle="Payment issues reported by customers and agents.">
        <template #actions>
            <div class="seg"><button v-for="s in ['open', 'resolved', 'all']" :key="s" class="capitalize" :aria-pressed="status === s" @click="setStatus(s)">{{ s }}</button></div>
        </template>
        <section class="panel">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Ticket</th><th>Customer</th><th>Issue</th><th>Transaction</th><th>Message</th><th>Opened</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="k in tickets.data" :key="k.id">
                            <td><strong class="text-heading">SUP-{{ k.id }}</strong><span class="chip ml-1.5" :class="k.status === 'resolved' ? 'chip-success' : 'chip-pending'">{{ k.status }}</span></td>
                            <td><Link :href="route('admin.users.show', k.user.id)" class="font-semibold text-accent-text">{{ k.user.name || k.user.phone }}</Link><span class="block text-[11px] text-muted">{{ k.user.phone }}</span></td>
                            <td>{{ types[k.type] }}</td>
                            <td><Link v-if="k.transaction" :href="route('admin.transactions.show', k.transaction.id)" class="font-semibold text-accent-text">{{ k.transaction.reference }}</Link><span v-if="k.transaction" class="block text-[11px] text-muted">{{ k.transaction.status }} · {{ naira(k.transaction.amount + k.transaction.fee) }}</span><span v-else class="text-muted">—</span></td>
                            <td class="max-w-[320px] whitespace-normal">{{ k.message }}<span v-if="k.reply" class="mt-1 block text-[11.5px] text-good">Reply: {{ k.reply }}</span></td>
                            <td class="text-muted">{{ dateTime(k.created_at) }}</td>
                            <td><button v-if="k.status === 'open'" class="btn btn-sm" @click="start(k)">Reply & resolve</button></td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!tickets.data.length" class="py-10 text-center text-[13px] text-muted">Nothing here. 🎉</p>
            </div>
            <div class="mt-4"><Pagination :links="tickets.links" /></div>
        </section>
        <Modal v-if="open" :title="`Reply to SUP-${open.id}`" @close="open = null">
            <div class="notice notice-info">{{ open.message }}</div>
            <div><label class="label mb-[7px]">Reply</label><textarea v-model="form.reply" rows="4" class="input" placeholder="e.g. We confirmed with the DisCo and refunded ₦5,100 to your wallet."></textarea></div>
            <label class="flex items-center gap-2 text-[13px]"><input v-model="form.notify" type="checkbox" />Also send by SMS</label>
            <p v-if="form.errors.reply" class="m-0 text-[13px] text-bad">{{ form.errors.reply }}</p>
            <div class="flex justify-end gap-2.5"><button class="btn btn-ghost" @click="open = null">Cancel</button><button class="btn" :disabled="form.processing || form.reply.length < 5" @click="send">Resolve</button></div>
        </Modal>
    </AdminLayout>
</template>
