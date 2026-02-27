<script setup>
import { ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import Banner from "@/Components/Banner.vue";
import AppTopbar from "./Partials/AppTopbar.vue";
import AppSidebar from "./Partials/AppSidebar.vue";
import AppFooter from "./Partials/AppFooter.vue";
import SystemToast from "./Partials/SystemToast.vue";
import ConfirmDialog from "@/Components/ConfirmDialog.vue";
import UndoToast from "@/Components/UndoToast.vue";

defineProps({
    title: String,
});
//console.log(usePage.props.locale);
//console.log($page);
const isSidebarOpen = ref(false);
</script>

<template>
    <div>
        <Head :title="title" />
        <Banner />

        <SystemToast />
        <ConfirmDialog />
        <UndoToast />
        <div
            class="min-h-screen bg-background text-text-main flex flex-col transition-colors duration-300"
        >
            <AppTopbar @toggleSidebar="isSidebarOpen = !isSidebarOpen" type="backend" />

            <AppSidebar :open="isSidebarOpen" @close="isSidebarOpen = false" />

            <header
                v-if="$slots.header"
                class="bg-surface shadow border-b border-border transition-colors duration-300"
            >
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-grow">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <slot />
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    </div>
</template>
