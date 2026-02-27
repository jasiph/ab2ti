<script setup>
import { Head } from "@inertiajs/vue3";
import SystemToast from "@/Layouts/Partials/SystemToast.vue"; // Tetap pasang ini!

defineProps({
    title: String,
});
</script>

<template>
    <div
        class="min-h-screen bg-background text-text-main font-sans antialiased"
    >
        <Head :title="title" />

        <SystemToast />

        <slot />
    </div>
</template>
