<script setup>
import { ref, useSlots, computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import SystemToast from "@/Layouts/Partials/SystemToast.vue";
import ApplicationMark from "@/Components/ApplicationMark.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";

// PROPS: Memberi opsi untuk mematikan navbar default secara eksplisit
const props = defineProps({
    title: String,
    withoutNavbar: { type: Boolean, default: false }, // Jika true, navbar hilang total
    sidebarType: { type: String, default: "sticky" }, // 'sticky' (Web style) atau 'drawer' (App style)
});

const slots = useSlots();
const page = usePage();
const user = computed(() => page.props.auth.user);

// State untuk Mobile Menu
const showingNavigationDropdown = ref(false); // Navbar menu
const showingMobileSidebar = ref(false); // Sidebar menu
</script>

<template>
    <div
        class="min-h-screen bg-background text-text-main font-sans selection:bg-primary selection:text-white"
    >
        <Head :title="title" />
        <SystemToast />

        <div v-if="!withoutNavbar">
            <slot name="navbar" v-if="$slots.navbar" />

            <nav
                v-else
                class="bg-surface/80 backdrop-blur-md border-b border-border sticky top-0 z-40"
            >
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <div class="shrink-0 flex items-center">
                                <Link href="/">
                                    <ApplicationMark
                                        class="block h-9 w-auto fill-primary"
                                    />
                                </Link>
                            </div>
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex items-center"
                            >
                                <Link
                                    href="/"
                                    class="text-text-muted hover:text-primary transition font-medium text-sm"
                                    >Home</Link
                                >
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <div v-if="user" class="ml-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button
                                            class="flex items-center gap-2 focus:outline-none transition"
                                        >
                                            <span
                                                class="text-sm font-medium text-text-main"
                                                >{{ user.name }}</span
                                            >
                                            <img
                                                class="h-8 w-8 rounded-full object-cover"
                                                :src="user.profile_photo_url"
                                                :alt="user.name"
                                            />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div
                                            class="block px-4 py-2 text-xs text-text-muted"
                                        >
                                            Manage Account
                                        </div>
                                        <DropdownLink :href="route('dashboard')"
                                            >Dashboard</DropdownLink
                                        >
                                        <DropdownLink
                                            :href="route('profile.show')"
                                            >Profile</DropdownLink
                                        >
                                        <div
                                            class="border-t border-border"
                                        ></div>
                                        <form
                                            @submit.prevent="
                                                $inertia.post(route('logout'))
                                            "
                                        >
                                            <DropdownLink as="button"
                                                >Log Out</DropdownLink
                                            >
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                            <div v-else class="flex items-center gap-4">
                                <Link
                                    :href="route('login')"
                                    class="text-sm font-medium text-text-muted hover:text-text-main"
                                    >Log in</Link
                                >
                                <BaseButton :href="route('register')" size="sm"
                                    >Get Started</BaseButton
                                >
                            </div>
                        </div>

                        <div class="-mr-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="p-2 rounded-md text-text-muted hover:text-text-main hover:bg-surface-variant focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-show="showingNavigationDropdown"
                    class="sm:hidden bg-surface border-t border-border shadow-lg"
                >
                    <div class="pt-2 pb-3 space-y-1 px-4">
                        <Link
                            href="/"
                            class="block py-2 text-base font-medium text-text-main border-b border-border"
                            >Home</Link
                        >
                    </div>
                    <div v-if="!user" class="p-4 grid grid-cols-2 gap-4">
                        <BaseButton
                            :href="route('login')"
                            variant="secondary"
                            class="w-full justify-center"
                            >Log in</BaseButton
                        >
                        <BaseButton
                            :href="route('register')"
                            variant="primary"
                            class="w-full justify-center"
                            >Register</BaseButton
                        >
                    </div>
                    <div v-else class="p-4">
                        <Link
                            :href="route('dashboard')"
                            class="block py-2 text-sm text-primary font-bold"
                            >Dashboard</Link
                        >
                    </div>
                </div>
            </nav>
        </div>

        <header
            v-if="$slots.header"
            class="bg-surface shadow border-b border-border"
        >
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="flex-grow">
            <div v-if="!$slots.sidebar" class="w-full">
                <slot />
            </div>

            <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row gap-8 items-start">
                    <aside class="w-full md:w-64 flex-shrink-0">
                        <div
                            v-if="sidebarType === 'sticky'"
                            class="md:hidden mb-4"
                        >
                            <button
                                @click="
                                    showingMobileSidebar = !showingMobileSidebar
                                "
                                class="w-full flex items-center justify-between px-4 py-3 bg-surface border border-border rounded-lg shadow-sm font-medium"
                            >
                                <span>Menu</span>
                                <svg
                                    class="h-5 w-5"
                                    :class="
                                        showingMobileSidebar ? 'rotate-180' : ''
                                    "
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>
                        </div>

                        <div
                            :class="[
                                sidebarType === 'sticky'
                                    ? 'md:sticky md:top-24'
                                    : '',
                                sidebarType === 'sticky' &&
                                !showingMobileSidebar
                                    ? 'hidden md:block'
                                    : 'block',
                            ]"
                        >
                            <slot name="sidebar" />
                        </div>
                    </aside>

                    <div class="flex-1 min-w-0 w-full">
                        <slot />
                    </div>
                </div>
            </div>
        </main>

        <footer
            v-if="!withoutNavbar"
            class="bg-surface border-t border-border mt-12 py-10"
        >
            <div
                class="max-w-7xl mx-auto px-4 text-center text-sm text-text-muted"
            >
                &copy; {{ new Date().getFullYear() }} AB2TI 2.0.
            </div>
        </footer>
    </div>
</template>
