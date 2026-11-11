<script setup>
import Pagination from '@/Components/Pagination.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime } from '@/lib/format';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ logs: Object });
const href = (l) => (l.subject_type === 'Transaction' ? route('admin.transactions.show', l.subject_id) : l.subject_type === 'User' ? route('admin.users.show', l.subject_id) : null);
</script>

<template>
    <Head title="Audit log" />
    <AdminLayout title="Audit log">
        <section class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                    <tr v-for="l in logs.data" :key="l.id">
                        <td class="whitespace-nowrap text-muted">{{ dateTime(l.created_at) }}</td>
                        <td class="whitespace-nowrap">{{ l.actor?.name || l.actor?.phone || 'system' }}</td>
                        <td class="mono whitespace-nowrap">{{ l.action }}</td>
                        <td><Link v-if="href(l)" :href="href(l)" class="font-semibold text-accent">{{ l.subject }}</Link><span v-else>{{ l.subject || '—' }}</span></td>
                        <td class="mono max-w-[380px] truncate text-[11.5px] text-muted" :title="JSON.stringify(l.data)">{{ JSON.stringify(l.data) }}</td>
                        <td class="mono text-muted">{{ l.ip }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        <div class="mt-4"><Pagination :links="logs.links" /></div>
    </AdminLayout>
</template>
