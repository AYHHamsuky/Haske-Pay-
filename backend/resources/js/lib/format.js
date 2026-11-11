/** All amounts from the server are kobo. */
export function naira(kobo, { kobo: showKobo = false } = {}) {
    const n = Number(kobo || 0) / 100;
    const dec = showKobo || !Number.isInteger(n) ? 2 : 0;
    return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: dec, maximumFractionDigits: 2 });
}

export const toKobo = (naira) => Math.round(Number(String(naira).replace(/[^\d.]/g, '')) * 100) || 0;

export const tokenGroups = (t) => (t ? t.match(/.{1,4}/g).join(' ') : '');

export function when(iso, locale = 'en') {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const hm = d.toLocaleTimeString('en-NG', { hour: '2-digit', minute: '2-digit' });
    if (d.toDateString() === now.toDateString()) return (locale === 'ha' ? 'Yau ' : 'Today ') + hm;
    const y = new Date(now);
    y.setDate(now.getDate() - 1);
    if (d.toDateString() === y.toDateString()) return (locale === 'ha' ? 'Jiya ' : 'Yesterday ') + hm;
    return d.toLocaleDateString('en-NG', { day: 'numeric', month: 'short' }) + ' ' + hm;
}

export const dateTime = (iso) => (iso ? new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—');

export const shortDay = (ymd) => new Date(ymd + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

export const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2));

export const digits = (v, max) => String(v ?? '').replace(/\D/g, '').slice(0, max);

const PREFIXES = {
    mtn: ['0803', '0806', '0703', '0706', '0813', '0816', '0810', '0814', '0903', '0906', '0913', '0916', '0704', '0707'],
    airtel: ['0802', '0808', '0708', '0812', '0701', '0902', '0901', '0904', '0907', '0912', '0911'],
    glo: ['0805', '0807', '0705', '0815', '0811', '0905', '0915'],
    '9mobile': ['0809', '0818', '0817', '0909', '0908'],
};
export function normPhone(p) {
    const d = digits(p, 13);
    return d.startsWith('234') && d.length === 13 ? '0' + d.slice(3) : d;
}
// VTpass sandbox test numbers (pending, anomaly, no response, timeout). The server only accepts them against the sandbox.
export const VTPASS_SANDBOX_NUMBERS = ['201000000000', '500000000000', '400000000000', '300000000000'];
export const phoneOk = (p) => /^0[789][01]\d{8}$/.test(normPhone(p)) || VTPASS_SANDBOX_NUMBERS.includes(digits(p, 13));
export function detectNetwork(p) {
    const pre = normPhone(p).slice(0, 4);
    return Object.keys(PREFIXES).find((k) => PREFIXES[k].includes(pre)) || null;
}
