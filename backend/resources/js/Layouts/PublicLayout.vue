<script setup>
import Flash from '@/Components/Flash.vue';
import Logo from '@/Components/Logo.vue';
import { brand } from '@/lib/brand';
import { lang, setLang, t } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ links: { type: Boolean, default: true } });
const page = usePage();
const home = (p) => (page.url === '/' ? `#${p}` : `/#${p}`);
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-surface text-heading">
        <header class="mx-auto flex h-[72px] w-full max-w-[1200px] items-center justify-between gap-6 px-[18px] md:h-[82px] md:px-7">
            <Link href="/" :aria-label="brand.name + ' home'"><Logo /></Link>
            <nav v-if="links" class="hidden items-center gap-[27px] text-[13px] font-semibold text-[#53677E] md:flex">
                <a :href="home('services')" class="hover:text-accent">{{ lang === 'ha' ? 'Sabis' : 'Services' }}</a>
                <a :href="home('how-it-works')" class="hover:text-accent">{{ lang === 'ha' ? 'Yadda yake aiki' : 'How it works' }}</a>
                <a :href="home('agents')" class="hover:text-accent">{{ lang === 'ha' ? 'Wakilai' : 'For agents' }}</a>
            </nav>
            <div class="flex items-center gap-2.5">
                <button class="rounded-full border border-line px-3 py-[7px] text-[12px] font-bold" :aria-label="lang === 'ha' ? 'Switch to English' : 'Canza zuwa Hausa'" @click="setLang(lang === 'ha' ? 'en' : 'ha')">{{ lang === 'ha' ? 'EN' : 'HA' }}</button>
                <Link v-if="page.props.auth" :href="page.props.auth.role === 'admin' ? '/admin' : '/app'" class="btn">{{ page.props.auth.role === 'admin' ? 'Admin' : t('myWallet') }}</Link>
                <template v-else>
                    <Link href="/login" class="btn btn-ghost hidden sm:inline-flex">{{ lang === 'ha' ? 'Shiga' : 'Sign in' }}</Link>
                    <Link href="/pay" class="btn">{{ t('payBill') }} <span aria-hidden="true">↗</span></Link>
                </template>
            </div>
        </header>
        <main class="flex-1"><slot /></main>
        <footer class="bg-[#0B213B] px-7 py-7 text-[#C6D5E4]">
            <div class="mx-auto flex max-w-[1200px] flex-col justify-between gap-5 text-[11.5px] sm:flex-row sm:items-center">
                <div><strong class="text-[15px] text-white">₦ {{ brand.name }}</strong><div class="mt-[7px]">{{ lang === 'ha' ? 'Hanya mafi sauƙi ta biyan kuɗaɗe na yau da kullum.' : 'A simpler way to manage everyday payments.' }}</div></div>
                <div class="flex flex-wrap gap-[18px]">
                    <a :href="home('services')">{{ lang === 'ha' ? 'Sabis' : 'Services' }}</a>
                    <a :href="home('how-it-works')">{{ lang === 'ha' ? 'Yadda yake aiki' : 'How it works' }}</a>
                    <a :href="home('agents')">{{ lang === 'ha' ? 'Wakilai' : 'Agents' }}</a>
                    <span>WhatsApp {{ page.props.support }}</span>
                </div>
                <div>© {{ new Date().getFullYear() }} {{ brand.name }} · Kaduna</div>
            </div>
        </footer>
        <Flash />
    </div>
</template>
