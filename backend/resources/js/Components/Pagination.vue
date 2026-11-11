<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ links: Array });
</script>

<template>
    <nav v-if="links && links.length > 3" class="flex flex-wrap gap-1.5" aria-label="Pagination">
        <template v-for="l in links" :key="l.label">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="pill" :aria-pressed="l.active" v-html="l.label" />
            <span v-else class="pill opacity-40" v-html="l.label" />
        </template>
    </nav>
</template>
