<script setup>
import Flash from '@/Components/Flash.vue';
import Icon from '@/Components/Icon.vue';
import Logo from '@/Components/Logo.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Navy sidebar + white top bar, from the BillPay demo.
 * groups: [{ label, items: [{ label, href, icon, match, exact, badge }] }]
 */
const props = defineProps({ groups: { type: Array, required: true }, crumbRoot: { type: String, default: 'Workspace' }, title: String, subtitle: String });
const page = usePage();
const open = ref(false);
const active = (item) => (item.exact ? page.url.split('?')[0] === item.match : page.url.startsWith(item.match));
const current = computed(() => props.groups.flatMap((g) => g.items).filter(active).sort((a, b) => b.match.length - a.match.length)[0]);
const initials = computed(() => (page.props.auth?.name || page.props.auth?.phone || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase());
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-dvh md:grid md:grid-cols-[210px_minmax(0,1fr)] lg:grid-cols-[248px_minmax(0,1fr)]">
        <div v-if="open" class="fixed inset-0 z-40 bg-[var(--scrim)] md:hidden" @click="open = false"></div>
        <div class="md:bg-navy">
        <aside
            class="fixed inset-y-0 left-0 z-50 w-[250px] flex-col gap-[25px] overflow-y-auto bg-navy px-4 py-[25px] text-white md:sticky md:top-0 md:z-auto md:flex md:h-dvh md:w-auto"
            :class="open ? 'flex' : 'hidden'"
        >
            <Link href="/" class="px-2.5"><Logo light /></Link>
            <template v-for="g in groups" :key="g.label">
                <div>
                    <div class="mb-[6px] px-[13px] text-[10px] tracking-[.15em] text-[#8DA6C2]">{{ g.label }}</div>
                    <nav class="grid gap-[6px]">
                        <Link
                            v-for="item in g.items"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-[10px] px-[13px] py-3 text-[14px] transition"
                            :class="active(item) ? 'bg-[var(--navy-hover)] text-white shadow-[inset_3px_0_#16C784]' : 'text-[#DCE7F4] hover:bg-[var(--navy-hover)] hover:text-white'"
                            @click="open = false"
                        >
                            <Icon :name="item.icon" :size="18" />
                            <span class="flex-1">{{ item.label }}</span>
                            <span v-if="item.badge" class="rounded-full bg-[#E99420] px-2 text-[11px] font-bold text-white">{{ item.badge }}</span>
                        </Link>
                    </nav>
                </div>
            </template>
            <div class="mt-auto border-t border-[var(--navy-line)] px-2.5 pt-4 text-[12px] leading-relaxed text-navy-muted">
                <template v-if="page.props.sandbox">Sandbox mode<br />VTpass &amp; Paystack are simulated.<br /></template>
                <button class="mt-2 flex items-center gap-2 font-semibold text-[#DCE7F4] hover:text-white" @click="logout"><Icon name="logout" :size="16" />Log out</button>
            </div>
        </aside>
        </div>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex h-[65px] items-center justify-between gap-3 border-b border-line bg-surface px-4 md:h-[76px] md:px-[34px]">
                <div class="flex min-w-0 items-center gap-3">
                    <button class="grid h-9 w-9 place-items-center rounded-lg bg-surface-2 md:hidden" aria-label="Open menu" @click="open = true"><Icon name="menu" :size="18" /></button>
                    <div class="truncate text-[13px] text-muted">{{ crumbRoot }} / <strong class="text-ink">{{ current?.label }}</strong></div>
                </div>
                <div class="flex items-center gap-2.5 md:gap-3.5">
                    <Link href="/" class="hidden rounded-lg border border-[#CFEADD] bg-accent-soft px-2.5 py-2 text-[11px] font-extrabold text-accent-text sm:inline-block">← Homepage</Link>
                    <span v-if="page.props.sandbox" class="demo-pill hidden lg:inline">● SANDBOX</span>
                    <div class="flex items-center gap-2.5 text-[13px] font-semibold">
                        <span class="grid h-[34px] w-[34px] place-items-center rounded-full bg-[#DCECE7] text-[12px] font-extrabold text-[#087A53]">{{ initials }}</span>
                        <span class="hidden sm:inline">{{ page.props.auth?.name || page.props.auth?.phone }}</span>
                    </div>
                </div>
            </header>
            <main class="mx-auto max-w-[1500px] px-3.5 py-[22px] md:px-5 md:py-6 lg:px-[34px] lg:py-[30px]">
                <div v-if="title || $slots.actions" class="heading">
                    <div><h1 v-if="title">{{ title }}</h1><p v-if="subtitle">{{ subtitle }}</p></div>
                    <div class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
                </div>
                <slot />
            </main>
        </div>
        <Flash />
    </div>
</template>
