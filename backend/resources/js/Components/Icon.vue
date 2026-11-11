<script setup>
defineProps({ name: { type: String, required: true }, size: { type: Number, default: 22 } });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="name === 'bolt'"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" /></template>
        <template v-else-if="name === 'phone'"><rect x="6" y="2.5" width="12" height="19" rx="2.5" /><path d="M10.5 18.5h3" /></template>
        <template v-else-if="name === 'wifi'"><path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.6 16a5 5 0 0 1 6.8 0" /><circle cx="12" cy="19.2" r="1.1" fill="currentColor" /></template>
        <template v-else-if="name === 'tv'"><rect x="2.5" y="5.5" width="19" height="13" rx="2" /><path d="M8 21.5h8M8 2.5l4 3 4-3" /></template>
        <template v-else-if="name === 'home'"><path d="M3.5 10.5 12 3.5l8.5 7V20a1 1 0 0 1-1 1h-5v-6h-5v6h-5a1 1 0 0 1-1-1v-9.5Z" /></template>
        <template v-else-if="name === 'list'"><path d="M8 6.5h12M8 12h12M8 17.5h12" /><circle cx="4" cy="6.5" r="1" fill="currentColor" /><circle cx="4" cy="12" r="1" fill="currentColor" /><circle cx="4" cy="17.5" r="1" fill="currentColor" /></template>
        <template v-else-if="name === 'user'"><circle cx="12" cy="8" r="4" /><path d="M4 21c1.2-4 4.4-6 8-6s6.8 2 8 6" /></template>
        <template v-else-if="name === 'users'"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c.9-3.4 3.4-5 6.5-5s5.6 1.6 6.5 5M16 4.6a3.5 3.5 0 0 1 0 6.8M18 15.3c1.7.6 3 2.1 3.5 4.7" /></template>
        <template v-else-if="name === 'back'"><path d="M15 5 8 12l7 7" /></template>
        <template v-else-if="name === 'chart'"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></template>
        <template v-else-if="name === 'layers'"><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 13 9 5 9-5" /></template>
        <template v-else-if="name === 'switch'"><rect x="2.5" y="7" width="19" height="10" rx="5" /><circle cx="16.5" cy="12" r="2.5" /></template>
        <template v-else-if="name === 'scale'"><path d="M12 3v18M5 21h14M4 7h16M7 7l-3 7a3 3 0 0 0 6 0L7 7Zm10 0-3 7a3 3 0 0 0 6 0l-3-7Z" /></template>
        <template v-else-if="name === 'shield'"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6L12 3Z" /><path d="m9 12 2 2 4-4" /></template>
        <template v-else-if="name === 'flask'"><path d="M9 3h6M10 3v6L4.5 19A1.5 1.5 0 0 0 5.8 21h12.4a1.5 1.5 0 0 0 1.3-2L14 9V3" /><path d="M7 15h10" /></template>
        <template v-else-if="name === 'upload'"><path d="M12 15V3m0 0-4 4m4-4 4 4M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" /></template>
        <template v-else-if="name === 'copy'"><rect x="8.5" y="8.5" width="12" height="12" rx="2" /><path d="M15.5 8.5V5.5a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h3" /></template>
        <template v-else-if="name === 'check'"><path d="m5 12.5 4.5 4.5L19 7.5" /></template>
        <template v-else-if="name === 'x'"><path d="M6 6l12 12M18 6 6 18" /></template>
        <template v-else-if="name === 'refresh'"><path d="M20 11a8 8 0 0 0-14.6-4.5L4 8m0-4v4h4M4 13a8 8 0 0 0 14.6 4.5L20 16m0 4v-4h-4" /></template>
        <template v-else-if="name === 'logout'"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10" /></template>
        <template v-else-if="name === 'search'"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></template>
        <template v-else-if="name === 'menu'"><path d="M4 7h16M4 12h16M4 17h16" /></template>
        <template v-else-if="name === 'grid'"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" /></template>
        <template v-else-if="name === 'arrow-up-right'"><path d="M7 17 17 7M8 7h9v9" /></template>
        <template v-else-if="name === 'arrow-right'"><path d="M5 12h14m-5-5 5 5-5 5" /></template>
        <template v-else-if="name === 'wallet'"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3" /><rect x="3.5" y="8" width="17" height="11.5" rx="2.5" /><circle cx="16.5" cy="13.75" r="1.2" fill="currentColor" /></template>
        <template v-else-if="name === 'store'"><path d="M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0M5.5 12v8h13v-8M10 20v-4.5h4V20" /></template>
        <template v-else-if="name === 'settings'"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z" /></template>
        <template v-else-if="name === 'help'"><circle cx="12" cy="12" r="9" /><path d="M9.5 9.2a2.6 2.6 0 0 1 5 .9c0 1.7-2.5 2.2-2.5 3.9" /><circle cx="12" cy="17.2" r=".9" fill="currentColor" /></template>
        <template v-else-if="name === 'download'"><path d="M12 4v11m0 0-4-4m4 4 4-4M4 19h16" /></template>
        <template v-else-if="name === 'plus'"><path d="M12 5v14M5 12h14" /></template>
        <template v-else-if="name === 'arrow-down'"><path d="M12 5v14m0 0-5-5m5 5 5-5" /></template>
        <template v-else-if="name === 'arrow-up'"><path d="M12 19V5m0 0-5 5m5-5 5 5" /></template>
        <template v-else-if="name === 'receipt'"><path d="M6 3.5h12v17l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3-2 1.3v-17Z" /><path d="M9 8h6M9 11.5h6M9 15h3.5" /></template>
        <template v-else-if="name === 'lock'"><rect x="4.5" y="10.5" width="15" height="10" rx="2" /><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" /></template>
    </svg>
</template>
