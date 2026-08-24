<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'inline-flex items-center min-h-control px-1 pt-1 border-b-2 border-primary text-sm font-medium leading-5 text-text-main whitespace-nowrap focus:outline-none focus-visible:border-primary-dark'
        : 'inline-flex items-center min-h-control px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-text-muted whitespace-nowrap hover:text-text-main hover:border-border focus:outline-none focus-visible:text-text-main focus-visible:border-border';
});
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
