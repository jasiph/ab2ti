<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-surface border border-border rounded-md font-semibold text-xs text-text-main uppercase tracking-widest shadow-sm hover:bg-surface-variant focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
