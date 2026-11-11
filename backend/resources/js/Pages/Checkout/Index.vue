<script setup>
import Icon from '@/Components/Icon.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { brand } from '@/lib/brand';
import { lang } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ discos: Array });

const c = computed(() => (lang.value === 'ha'
    ? {
        badge: 'BIYAN KUƊAƊEN KULLUM, CIKIN SAUƘI', h1a: 'Biya wuta. Sayi data.', h1b: 'Rayuwa ta ci gaba.',
        sub: 'Wuri ɗaya mai sauƙi don biyan kuɗin wuta, saka katin waya da sayen data. Biya cikin sauri daga walat ɗinka, a duk inda kake.',
        cta1: 'Fara biyan kuɗi', cta2: 'Shiga walat ɗina', foot: 'Duk kamfanonin wuta 12 · Katin waya · Data',
        k1: 'Dandali ɗaya, buƙatun yau da kullum', h2: 'Kuɗaɗen da kake biya, a wuri ɗaya.', s2: 'Zaɓi abin da kake buƙata ka bi matakai masu sauƙi.',
        k2: 'Yadda yake aiki', h3: 'Matakai uku. Babu wahala.', s3: 'Matakai bayyanannu don ka san abin da ya faru da kuɗinka.',
        agentH: 'Kana da kasuwancin biyan kuɗi?', agentP: 'Wakilai ba sa biyan kuɗin sabis, suna samun lada a kowace siyarwa, kuma suna iya siyar wa mita da yawa a lokaci ɗaya.', agentB: 'Shiga a matsayin wakili',
    }
    : {
        badge: 'YOUR EVERYDAY PAYMENTS, MADE SIMPLE', h1a: 'Pay bills. Buy data.', h1b: 'Keep life moving.',
        sub: 'One simple place to pay electricity bills, top up airtime and buy data bundles. Fast, convenient payments from your wallet, wherever you are.',
        cta1: 'Start paying bills', cta2: 'Open my wallet', foot: 'All 12 DisCos · Airtime · Data bundles',
        k1: 'One platform, everyday essentials', h2: 'The bills you pay, all in one place.', s2: 'Skip switching between apps. Choose what you need and follow a simple guided payment flow.',
        k2: 'How it works', h3: 'Three steps. Less hassle.', s3: 'A clear flow helps you check the details and know exactly what happened to your payment.',
        agentH: 'Run a bill-payment business?', agentP: 'Agents pay no service fee, earn commission on every sale, and can vend a whole estate of meters in one batch.', agentB: 'Sign in as an agent',
    }));

const tiles = computed(() => (lang.value === 'ha'
    ? [['electricity', 'bolt', '', 'Kuɗin wuta', 'Biya kafin amfani ko bayan amfani. Za mu tabbatar da sunan mai mita kafin ka biya, kuma token yana zuwa ta SMS.', 'Biya wuta'],
        ['airtime', 'phone', 'orange', 'Katin waya', 'MTN, Airtel, Glo ko 9mobile. Shigar da lamba, zaɓi adadi, ka duba kafin ka tabbatar.', 'Sayi kati'],
        ['data', 'wifi', 'blue', 'Data', 'Zaɓi tsarin data da ya dace da kai ka aika zuwa lambarka ko ta wani.', 'Sayi data']]
    : [['electricity', 'bolt', '', 'Electricity bills', 'Prepaid tokens or postpaid bills for every DisCo. We confirm the name on the meter before you pay, and text you the token.', 'Pay electricity'],
        ['airtime', 'phone', 'orange', 'Airtime top-up', 'Recharge MTN, Airtel, Glo or 9mobile. Enter a number, choose the amount and review before confirming.', 'Buy airtime'],
        ['data', 'wifi', 'blue', 'Data bundles', 'Choose a bundle that suits you and send it to your number or someone else’s in a few steps.', 'Buy data']]));

const benefits = computed(() => (lang.value === 'ha'
    ? [['wallet', 'Walat ɗaya', 'Saka kuɗi ta transfa zuwa lambar asusunka ta dindindin.'], ['receipt', 'Tarihi bayyananne', 'Duba lambobin shaida da matsayin kowane biya.'], ['refresh', 'Kuɗinka yana lafiya', 'Idan token bai fito ba, kuɗin yana dawowa kai tsaye.'], ['store', 'Na wakilai', 'Dashbod na musamman don wakilan POS.']]
    : [['wallet', 'One wallet', 'Fund it by bank transfer to your own permanent account number.'], ['receipt', 'Clear history', 'Every payment has a reference and a status you can check.'], ['refresh', 'Automatic refunds', 'If a token fails, the money goes straight back to your wallet.'], ['store', 'Agent-ready', 'A dedicated dashboard for POS agents, with bulk vending.']]));

const steps = computed(() => (lang.value === 'ha'
    ? [['Zaɓi sabis', 'Wuta, katin waya ko data. Shigar da lambar mita ko waya da adadi.'], ['Duba bayanai', 'Tabbatar da kamfani, mai karɓa da jimilla kafin ka biya.'], ['Karɓi token', 'Token yana bayyana nan take kuma yana zuwa ta SMS. Idan ya jinkirta, muna ci gaba da dubawa.']]
    : [['Choose a service', 'Electricity, airtime or data. Add the meter or phone number and an amount.'], ['Review the details', 'Confirm the provider, recipient and total before you pay.'], ['Get your token', 'It appears on screen and by SMS. If the provider is slow, we keep checking until it resolves.']]));
</script>

<template>
    <Head :title="`${brand.name} · Electricity tokens, airtime and data`" />
    <PublicLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden bg-[radial-gradient(circle_at_80%_10%,#245b7b_0,transparent_35%),linear-gradient(115deg,#0d2748_0%,#123b60_62%,#11644e_100%)] text-white">
            <div class="pointer-events-none absolute -right-[140px] -bottom-[290px] h-[600px] w-[600px] rounded-full border border-white/10 shadow-[0_0_0_55px_#ffffff06,0_0_0_110px_#ffffff04]"></div>
            <div class="relative mx-auto grid max-w-[1200px] items-center gap-11 px-5 pt-[50px] pb-[62px] md:px-7 md:pt-[58px] lg:grid-cols-[1.08fr_.92fr] lg:gap-[52px] lg:pt-[76px] lg:pb-[82px]">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[.07] px-3 py-2 text-[11px] font-bold tracking-[.02em] text-[#DCEFE8]">
                        <span class="h-[7px] w-[7px] rounded-full bg-[#2BE29B] shadow-[0_0_0_4px_#2be29b20]"></span>{{ c.badge }}
                    </div>
                    <h1 class="mt-[22px] mb-[19px] max-w-[640px] text-[clamp(40px,5.1vw,64px)] leading-[1.04] font-extrabold tracking-[-.045em] !text-white">{{ c.h1a }}<br /><em class="text-[#45E2A5] not-italic">{{ c.h1b }}</em></h1>
                    <p class="mt-0 mb-[27px] max-w-[520px] text-[15px] leading-[1.8] text-[#D1DFED] md:text-[16px]">{{ c.sub }}</p>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Link href="/pay" class="btn btn-lg">{{ c.cta1 }} <Icon name="arrow-right" :size="16" /></Link>
                        <Link :href="$page.props.auth ? '/app' : '/login'" class="btn btn-lg btn-light">{{ c.cta2 }}</Link>
                    </div>
                    <div class="mt-[18px] text-[11.5px] text-[#A9BFD3]">⚡ {{ c.foot }}</div>
                </div>

                <!-- Phone mockup -->
                <div class="relative mx-auto w-full max-w-[470px] lg:justify-self-end" aria-hidden="true">
                    <div class="mx-auto w-[min(100%,390px)] rotate-1 rounded-[28px] border-[5px] border-[#DCE6EE] bg-white p-3.5 text-[#142F50] shadow-[0_30px_80px_#061a2e65] sm:border-[7px] sm:p-[18px]">
                        <div class="mb-5 flex items-center justify-between text-[11px] text-[#718096]"><b class="text-[#12395D]">◉ {{ brand.short }}</b><span>●●● 4G ▰</span></div>
                        <div class="text-[12px] text-[#75859A]">Sannu, Aisha 👋</div>
                        <div class="my-[9px] mb-[17px] rounded-[15px] bg-[linear-gradient(135deg,#112d50,#1a5780)] p-[19px] text-white">
                            <small class="text-[10px] tracking-[.08em] text-[#C5D7E6]">AVAILABLE WALLET BALANCE</small>
                            <strong class="my-[9px] mb-4 block text-[29px] font-extrabold">₦27,900.00</strong>
                            <div class="flex items-center justify-between text-[10px] text-[#C5D7E6]"><span>Wema Bank · 7800015838</span><b class="rounded-[7px] bg-white px-2.5 py-2 text-[#133A5C]">＋ Add money</b></div>
                        </div>
                        <div class="mb-[11px] text-[12px] font-extrabold">What would you like to pay?</div>
                        <div class="grid grid-cols-3 gap-[9px]">
                            <div v-for="[i, l, cls] in [['bolt', 'Electricity', ''], ['phone', 'Airtime', 'orange'], ['wifi', 'Data', 'blue']]" :key="l" class="grid justify-items-center rounded-[11px] border border-[#E5EBF1] px-1 py-3 text-[10px] font-bold">
                                <span class="icon-tile !mb-[7px] !h-9 !w-9" :class="cls"><Icon :name="i" :size="18" /></span>{{ l }}
                            </div>
                        </div>
                        <div class="mt-[17px] border-t border-[#EDF1F5] pt-3.5">
                            <div class="mb-[11px] flex justify-between text-[11px] font-extrabold"><span>Recent payments</span><span class="text-[#078B59]">View all</span></div>
                            <div v-for="[i, cls, n, s, a] in [['bolt', '', 'Kaduna Electric', 'Prepaid token', '₦3,000'], ['phone', 'orange', 'MTN Airtime', 'Top up', '₦1,000']]" :key="n" class="mt-2.5 flex items-center gap-[9px] text-[10px]">
                                <span class="icon-tile !mb-0 !h-[29px] !w-[29px] !rounded-[9px]" :class="cls"><Icon :name="i" :size="14" /></span>
                                <span><strong class="block">{{ n }}</strong><small class="text-[#8290A3]">{{ s }}</small></span>
                                <span class="ml-auto font-extrabold">{{ a }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-[22px] -right-[3px] flex items-center gap-2 rounded-xl bg-white px-[15px] py-3 text-[11px] font-extrabold text-[#183753] shadow-[0_10px_30px_#061a2e35] sm:top-10 sm:-right-[11px]">
                        <span class="text-[21px] text-[#07945F]">✓</span><div>Token in seconds<br /><small class="font-medium text-[#7B8B9B]">and by SMS</small></div>
                    </div>
                    <div class="absolute bottom-[35px] -left-[3px] rounded-xl bg-white px-4 py-[13px] text-[11px] font-extrabold text-[#183753] shadow-[0_10px_30px_#061a2e35] sm:bottom-[54px] sm:-left-7">
                        🔒 Refunds are automatic<small class="mt-1 block font-medium text-[#718096]">if a token fails</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services -->
        <section id="services" class="mx-auto max-w-[1200px] px-5 py-[55px] md:px-7 md:py-[74px]">
            <div class="mx-auto mb-[35px] max-w-[670px] text-center">
                <div class="kicker mb-3">{{ c.k1 }}</div>
                <h2 class="m-0 mb-[13px] text-[clamp(27px,3vw,38px)] leading-[1.15] font-extrabold tracking-[-.03em]">{{ c.h2 }}</h2>
                <p class="m-0 text-[14px] leading-[1.75] text-muted">{{ c.s2 }}</p>
            </div>
            <div class="grid gap-[17px] md:grid-cols-3">
                <article v-for="[k, i, cls, h, p, link] in tiles" :key="k" class="rounded-2xl border border-[#E3EAF1] bg-surface p-5 shadow-[0_5px_22px_#102b4e06] transition hover:-translate-y-[3px] hover:border-[#B7E8D3] hover:shadow-[0_12px_30px_#102b4e0c] md:p-[25px]">
                    <span class="icon-tile !mb-[19px] !h-[52px] !w-[52px] !rounded-[15px]" :class="cls"><Icon :name="i" :size="25" /></span>
                    <h3 class="m-0 mb-[9px] text-[17px] font-bold">{{ h }}</h3>
                    <p class="m-0 mb-[17px] text-[13px] leading-[1.7] text-muted">{{ p }}</p>
                    <Link :href="`/pay?c=${k}`" class="text-[12.5px] font-extrabold text-accent-text">{{ link }} →</Link>
                </article>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-[9px] md:grid-cols-4 md:gap-[15px]">
                <div v-for="[i, b, p] in benefits" :key="b" class="rounded-[13px] border border-[#E7EDF3] bg-[#F7FAFC] p-3.5 md:p-[19px] dark:bg-surface-2">
                    <Icon :name="i" :size="21" class="text-accent-text" />
                    <b class="mt-2.5 mb-1.5 block text-[13px] text-heading">{{ b }}</b>
                    <p class="m-0 text-[11.5px] leading-[1.6] text-muted">{{ p }}</p>
                </div>
            </div>
            <div class="mt-8 flex flex-wrap justify-center gap-1.5">
                <span v-for="d in discos" :key="d.code" class="rounded-full border border-line px-3 py-1.5 text-[12px] font-bold text-heading" :class="{ 'opacity-50': !d.active }">{{ d.short_name }} <span class="font-normal text-muted">· {{ d.name.replace(' Electric', '').replace(' Power', '') }}</span></span>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="border-y border-[#E8EDF3] bg-[#F5F8FB] dark:bg-bg">
            <div class="mx-auto max-w-[1200px] px-5 py-[55px] md:px-7 md:py-[74px]">
                <div class="mx-auto max-w-[670px] text-center">
                    <div class="kicker mb-3">{{ c.k2 }}</div>
                    <h2 class="m-0 mb-[13px] text-[clamp(27px,3vw,38px)] leading-[1.15] font-extrabold tracking-[-.03em]">{{ c.h3 }}</h2>
                    <p class="m-0 text-[14px] leading-[1.75] text-muted">{{ c.s3 }}</p>
                </div>
                <div class="mt-9 grid gap-[22px] md:grid-cols-3">
                    <div v-for="([h, p], i) in steps" :key="h" class="flex gap-[15px] rounded-[14px] border border-[#E3EAF1] bg-surface p-[21px]">
                        <div class="grid h-[38px] w-[38px] flex-none place-items-center rounded-xl bg-accent-soft font-black text-accent-text">0{{ i + 1 }}</div>
                        <div><h3 class="mt-0.5 mb-2 text-[14px] font-bold">{{ h }}</h3><p class="m-0 text-[12.5px] leading-[1.65] text-muted">{{ p }}</p></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Agents -->
        <section id="agents" class="px-3.5 py-[45px] md:px-[18px] md:py-[72px]">
            <div class="mx-auto flex max-w-[1144px] flex-col items-start justify-between gap-6 rounded-[20px] bg-[linear-gradient(115deg,#0f2c4d,#145a55)] p-7 text-white md:flex-row md:items-center md:px-10 md:py-[37px]">
                <div>
                    <h2 class="m-0 mb-[9px] text-[25px] font-extrabold tracking-[-.03em] !text-white">{{ c.agentH }}</h2>
                    <p class="m-0 max-w-[580px] text-[13px] leading-[1.7] text-[#C7D9E0]">{{ c.agentP }}</p>
                </div>
                <Link href="/login" class="btn btn-lg btn-light">{{ c.agentB }} <Icon name="arrow-right" :size="16" /></Link>
            </div>
        </section>
    </PublicLayout>
</template>
