import { ref } from 'vue';

const STR = {
    en: {
        overview: 'Overview', payBill: 'Pay a bill', myWallet: 'My wallet', transactions: 'Transactions', agentDashboard: 'Agent dashboard',
        bulkVending: 'Bulk vending', commission: 'Commission', profileSecurity: 'Profile & security', help: 'Help & support',
        morning: 'Good morning', afternoon: 'Good afternoon', evening: 'Good evening', overviewSub: 'Pay bills in seconds. Manage your wallet from one place.',
        makePayment: 'Make a payment', whatPay: 'What would you like to pay?', chooseService: 'Choose a service', recentTx: 'Recent transactions', viewAll: 'View all',
        quickActions: 'Quick actions', successful: 'Successful payments', totalSpent: 'Total spent', savedCount: 'Saved meters', available: 'Available to spend',
        acrossServices: 'Across all services', successfulTx: 'Successful transactions', quickRepeat: 'Quick repeat payments',
        elecSub: 'Buy prepaid tokens or pay postpaid bills', airSub: 'Top up all major networks', dataSub: 'Stay online with data plans',
        payElec: 'Pay electricity', buyAir: 'Buy airtime', buyData: 'Buy data', pay2: 'Pay', buy2: 'Buy',
        paySub: 'Choose a service and complete your payment securely.', step1: '1. Select service', step2: '2. Enter payment details', stepOf: 'Step {n} of 3',
        review: 'Review payment', confirmPay: 'Confirm payment', confirmSub: 'Please review the details before submitting.', cancel: 'Cancel',
        payFlow: 'Payment flow', flow1: 'Enter details', flow1s: 'Choose provider and amount', flow2: 'Review & confirm', flow2s: 'Check your details and enter your PIN',
        flow3: 'Provider processing', flow3s: 'Success, pending or refunded', pinLabel: 'Transaction PIN',
        walletSub: 'Manage your balance and review wallet activity.', fundWallet: 'Fund wallet', availableBalance: 'AVAILABLE BALANCE', walletActivity: 'Wallet activity',
        allActivity: 'All activity', credits: 'Credits', debits: 'Debits', fundTransfer: 'Fund with bank transfer',
        txSub: 'Track purchases, provider references and refunds.', exportCsv: 'Export CSV', allTx: 'All transactions', searchRef: 'Search reference…', allStatuses: 'All statuses', detailsBtn: 'Details',
        reportIssue: 'Report a payment issue', faq: 'Frequently asked questions', issueType: 'Issue type', describe: 'Describe the issue', submit: 'Submit request',
        helpSub: 'Get help with a payment or wallet issue.', profileSub: 'Manage your account and security.', personal: 'Personal information', securityChecklist: 'Security',
        home: 'Home', history: 'History', profile: 'Profile', balance: 'Wallet balance', addMoney: 'Add money',
        elec: 'Electricity', airtime: 'Airtime', data: 'Data', tv: 'TV', soon: 'Soon', recent: 'Recent activity', seeAll: 'See all',
        disco: 'Distribution company', meterType: 'Meter type', prepaid: 'Prepaid', postpaid: 'Postpaid', meterNo: 'Meter number',
        verify: 'Verify meter', verifying: 'Checking meter…', change: 'Change', verified: 'Verified', amount: 'Amount', cont: 'Continue',
        fee: 'Service fee', total: 'You pay', units: 'Estimated units', tokenValue: 'Token value', pin: 'Enter your PIN',
        success: 'Successful', pending: 'Pending', failed: 'Failed', refunded: 'Refunded', token: 'Token', copyToken: 'Copy token',
        phone: 'Phone number', network: 'Network', buy: 'Buy', plan: 'Choose a plan', saved: 'Saved meters',
        done: 'Done', checkNow: 'Check now', all: 'All', funding: 'Top-ups', bills: 'Bills',
        todayComm: "Today's commission", feeWaived: 'Fee waived for your customers',
        ref: 'Reference', customer: 'Customer', route: 'Route', when: 'Date', attempts: 'Status checks',
        pendingMsg: 'We are confirming with the provider. Your money is safe: if this fails, it returns to your wallet automatically.',
        refundMsg: 'returned to your wallet.', postpaidMsg: 'Payment applied to the customer account. No token is needed for postpaid meters.',
        vaTitle: 'Your account number', vaSub: 'Transfer from any bank app. Money lands in your wallet in seconds.',
        noTx: 'No transactions yet. Buy a token to see it here.', processing: 'Processing',
        payNow: 'Pay', yourPhone: 'Your phone number', yourPhoneHint: 'We text the token here. Your receipt and any refund are kept under this number.',
        heroTitle: 'Light up in seconds.', heroSub: 'Buy prepaid electricity tokens, airtime and data for every DisCo in Nigeria. No app needed.',
        secured: 'Payments secured by Paystack', getApp: 'Get the app', login: 'Log in', logout: 'Log out',
        receipt: 'Receipt', sendSms: 'Send by SMS', backHome: 'Buy another',
    },
    ha: {
        overview: 'Taƙaitawa', payBill: 'Biya kuɗi', myWallet: 'Walat ɗina', transactions: 'Ma’amaloli', agentDashboard: 'Dashbod na wakili',
        bulkVending: 'Siyarwa da yawa', commission: 'Lada', profileSecurity: 'Bayanai da tsaro', help: 'Taimako',
        morning: 'Barka da safiya', afternoon: 'Barka da rana', evening: 'Barka da yamma', overviewSub: 'Biya kuɗin wuta da waya cikin daƙiƙa. Sarrafa walat ɗinka a wuri ɗaya.',
        makePayment: 'Yi biya', whatPay: 'Me kake so ka biya?', chooseService: 'Zaɓi sabis', recentTx: 'Ma’amaloli na baya-bayan nan', viewAll: 'Duba duka',
        quickActions: 'Ayyuka masu sauri', successful: 'Biyan da ya yi nasara', totalSpent: 'Jimillar kashewa', savedCount: 'Mitocin da aka ajiye', available: 'Akwai don kashewa',
        acrossServices: 'A duk sabis', successfulTx: 'Ma’amaloli masu nasara', quickRepeat: 'Sake biya cikin sauri',
        elecSub: 'Sayi token ko biya bayan amfani', airSub: 'Saka kati a duk kamfanoni', dataSub: 'Ci gaba da intanet', 
        payElec: 'Biya wuta', buyAir: 'Sayi kati', buyData: 'Sayi data', pay2: 'Biya', buy2: 'Saya',
        paySub: 'Zaɓi sabis ka kammala biyanka lafiya.', step1: '1. Zaɓi sabis', step2: '2. Shigar da bayanai', stepOf: 'Mataki {n} cikin 3',
        review: 'Duba biyan', confirmPay: 'Tabbatar da biya', confirmSub: 'Duba bayanan kafin ka tura.', cancel: 'Soke',
        payFlow: 'Yadda biya ke tafiya', flow1: 'Shigar da bayanai', flow1s: 'Zaɓi kamfani da adadi', flow2: 'Duba ka tabbatar', flow2s: 'Duba bayanai ka saka PIN',
        flow3: 'Kamfani yana aiki', flow3s: 'Nasara, jira ko maido da kuɗi', pinLabel: 'PIN na ma’amala',
        walletSub: 'Sarrafa kuɗinka ka duba ayyukan walat.', fundWallet: 'Saka kuɗi', availableBalance: 'KUƊIN DA AKE DA SHI', walletActivity: 'Ayyukan walat',
        allActivity: 'Duka', credits: 'Shiga', debits: 'Fita', fundTransfer: 'Saka kuɗi ta transfa',
        txSub: 'Bibiyar siyayya, lambobin shaida da maido da kuɗi.', exportCsv: 'Fitar da CSV', allTx: 'Duk ma’amaloli', searchRef: 'Nemi lamba…', allStatuses: 'Duk matsayi', detailsBtn: 'Bayani',
        reportIssue: 'Kai rahoton matsala', faq: 'Tambayoyi da ake yawan yi', issueType: 'Irin matsala', describe: 'Bayyana matsalar', submit: 'Tura',
        helpSub: 'Samu taimako game da biya ko walat.', profileSub: 'Sarrafa asusunka da tsaro.', personal: 'Bayanan kai', securityChecklist: 'Tsaro',
        home: 'Gida', history: 'Tarihi', profile: 'Bayanai', balance: 'Kuɗin walat', addMoney: 'Saka kuɗi',
        elec: 'Wutar lantarki', airtime: 'Katin waya', data: 'Data', tv: 'Talabijin', soon: 'Nan gaba', recent: 'Ayyukan baya-bayan nan', seeAll: 'Duba duka',
        disco: 'Kamfanin wuta', meterType: 'Irin mita', prepaid: 'Kafin amfani', postpaid: 'Bayan amfani', meterNo: 'Lambar mita',
        verify: 'Tabbatar da mita', verifying: 'Ana duba mita…', change: 'Canza', verified: 'An tabbatar', amount: 'Adadin kuɗi', cont: 'Ci gaba',
        fee: 'Kuɗin sabis', total: 'Jimilla', units: 'Kimanin unit', tokenValue: 'Darajar token', pin: 'Shigar da PIN ɗinka',
        success: 'An yi nasara', pending: 'Ana jira', failed: 'Bai yi nasara ba', refunded: 'An mayar da kuɗi', token: 'Token', copyToken: 'Kwafi token',
        phone: 'Lambar waya', network: 'Kamfanin sadarwa', buy: 'Saya', plan: 'Zaɓi tsari', saved: 'Mitocin da aka ajiye',
        done: 'Shi ke nan', checkNow: 'Duba yanzu', all: 'Duka', funding: 'Saka kuɗi', bills: 'Biyan kuɗi',
        todayComm: 'Ladan yau', feeWaived: 'Babu kuɗin sabis ga abokan ciniki',
        ref: 'Lambar shaida', customer: 'Abokin ciniki', route: 'Hanya', when: 'Lokaci', attempts: 'Bincike',
        pendingMsg: 'Muna tabbatarwa da kamfanin. Kuɗinka yana lafiya: idan bai yi nasara ba, zai dawo walat ɗinka kai tsaye.',
        refundMsg: 'an mayar walat ɗinka.', postpaidMsg: 'An biya asusun abokin ciniki. Mitar bayan amfani ba ta buƙatar token.',
        vaTitle: 'Lambar asusunka', vaSub: 'Tura daga kowace manhajar banki. Kuɗin zai shiga walat ɗinka nan take.',
        noTx: 'Babu wani aiki tukuna. Sayi token don ganin sa a nan.', processing: 'Ana aiki',
        payNow: 'Biya', yourPhone: 'Lambar wayarka', yourPhoneHint: 'Za mu aiko token ta wannan lambar. Rasiti da duk wani maido da kuɗi suna ƙarƙashin ta.',
        heroTitle: 'Haske cikin daƙiƙa.', heroSub: 'Sayi token na wutar lantarki, katin waya da data na duk kamfanonin wuta a Najeriya. Ba sai ka saukar da manhaja ba.',
        secured: 'Paystack ne ke tsare biyan kuɗi', getApp: 'Saukar da manhaja', login: 'Shiga', logout: 'Fita',
        receipt: 'Rasiti', sendSms: 'Aika ta SMS', backHome: 'Sake saya',
    },
};

function initial() {
    try {
        return localStorage.getItem('haske.lang') || 'en';
    } catch {
        return 'en';
    }
}

export const lang = ref(initial());

export function setLang(l) {
    lang.value = l;
    try {
        localStorage.setItem('haske.lang', l);
    } catch {}
    document.documentElement.lang = l;
}

export const t = (k, vars = {}) => Object.entries(vars).reduce((s, [a, b]) => s.replace(`{${a}}`, b), STR[lang.value]?.[k] ?? STR.en[k] ?? k);

export function greeting() {
    const h = new Date().getHours();
    return t(h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening');
}
