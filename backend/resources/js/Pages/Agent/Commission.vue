<script setup>
import BarChart from '@/Components/BarChart.vue';
import Stat from '@/Components/Stat.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { naira, shortDay } from '@/lib/format';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({ report: Object, services: Object });
const setDays = (d) => router.get(route('agent.commission'), { days: d }, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Commission" />
    <AppLayout title="Commission">
        <template #actions>
            <div class="seg"><button v-for="d in [7, 30, 90]" :key="d" :aria-pressed="report.period.days === d" @click="setDays(d)">{{ d }} days</button></div>
        </template>
        <div class="grid gap-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Commission today" :value="naira(report.today.commission)" :note="`${report.today.sales} sales`" tone="good" />
                <Stat :label="`Commission, ${report.period.days} days`" :value="naira(report.period.commission)" :note="`${report.tier} tier · you keep ${report.share}%`" />
                <Stat label="Sales" :value="report.period.sales.toLocaleString()" note="delivered only" />
                <Stat label="Value vended" :value="naira(report.period.volume)" note="face value of tokens and airtime" />
            </div>
            <section class="card p-5">
                <h2 class="m-0 mb-3 text-[16px]">Commission by day</h2>
                <BarChart :rows="report.series" :series="[{ key: 'commission', label: 'Commission', color: 'var(--good)' }]" :format="naira" />
            </section>
            <div class="grid gap-5 lg:grid-cols-2">
                <section class="card overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Service</th><th class="text-right">Sales</th><th class="text-right">Vended</th><th class="text-right">Commission</th></tr></thead>
                        <tbody>
                            <tr v-for="r in report.by_service" :key="r.service_code"><td>{{ services[r.service_code] || r.service_code }}</td><td class="num text-right">{{ r.sales }}</td><td class="num text-right">{{ naira(r.volume) }}</td><td class="num text-right font-semibold text-good">{{ naira(r.commission) }}</td></tr>
                            <tr v-if="!report.by_service.length"><td colspan="4" class="py-8 text-center text-muted">No sales in this period yet.</td></tr>
                        </tbody>
                    </table>
                </section>
                <section class="card overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Day</th><th class="text-right">Sales</th><th class="text-right">Vended</th><th class="text-right">Commission</th></tr></thead>
                        <tbody>
                            <tr v-for="r in [...report.series].reverse().filter((r) => r.sales)" :key="r.day"><td>{{ shortDay(r.day) }}</td><td class="num text-right">{{ r.sales }}</td><td class="num text-right">{{ naira(r.volume) }}</td><td class="num text-right font-semibold">{{ naira(r.commission) }}</td></tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
