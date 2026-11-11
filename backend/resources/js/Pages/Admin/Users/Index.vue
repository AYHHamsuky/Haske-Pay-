<script setup>
import Pagination from '@/Components/Pagination.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { dateTime, naira } from '@/lib/format';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ users: Object, filters: Object });
const f = reactive({ q: '', role: '', status: '', ...props.filters });
let timer;
const apply = () => router.get(route('admin.users'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const search = () => { clearTimeout(timer); timer = setTimeout(apply, 300); };
</script>

<template>
    <Head title="Users" />
    <AdminLayout title="Users & wallets">
        <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
            <input v-model="f.q" class="input sm:col-span-2" placeholder="Phone, name or business" @input="search" />
            <select v-model="f.role" class="input" @change="apply"><option value="">Any role</option><option>customer</option><option>agent</option><option>admin</option></select>
            <select v-model="f.status" class="input" @change="apply"><option value="">Any status</option><option>active</option><option>suspended</option></select>
        </div>
        <section class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Phone</th><th>Role</th><th class="text-right">Balance</th><th>Last login</th><th>Joined</th></tr></thead>
                <tbody>
                    <tr v-for="u in users.data" :key="u.id" class="cursor-pointer" @click="router.visit(route('admin.users.show', u.id))">
                        <td><b>{{ u.name || '—' }}</b><span v-if="u.business" class="block text-[12px] text-muted">{{ u.business }}</span></td>
                        <td class="mono">{{ u.phone }}</td>
                        <td><span class="chip" :class="u.role === 'agent' ? 'chip-success' : u.role === 'admin' ? 'chip-pending' : 'bg-surface-2'">{{ u.role }}{{ u.tier ? ' · ' + u.tier : '' }}</span><span v-if="u.status === 'suspended'" class="chip chip-failed ml-1">suspended</span></td>
                        <td class="num text-right font-semibold">{{ naira(u.balance) }}</td>
                        <td class="text-muted">{{ dateTime(u.last_login_at) }}</td>
                        <td class="text-muted">{{ dateTime(u.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        <div class="mt-4"><Pagination :links="users.links" /></div>
    </AdminLayout>
</template>
