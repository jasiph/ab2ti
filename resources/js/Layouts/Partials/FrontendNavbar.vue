<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import NavLink from "@/Components/NavLink.vue";
import { useNavigation } from "@/Composables/useNavigation";
import ApplicationLogoWithText from "@/Components/ApplicationLogoWithText.vue";

defineEmits(["toggleSidebar"]);

const { frontendMenus } = useNavigation();
const page = usePage();
</script>

<template>
    <nav
        class="bg-surface/90 backdrop-blur-md border-b border-border sticky top-0 z-30"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- LEFT: Logo & Mobile Toggle -->
                <div class="flex items-center">
                    <!-- Mobile Hamburger -->
                    <div class="-ml-2 mr-2 flex items-center sm:hidden">
                        <button
                            @click="$emit('toggleSidebar')"
                            class="inline-flex items-center justify-center p-2 rounded-md text-text-muted hover:text-text-main hover:bg-surface-variant focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary"
                        >
                            <span class="sr-only">Open main menu</span>
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Logo -->
                    <div class="shrink-0 flex items-center gap-2">
                        <ApplicationLogoWithText />
                    </div>
                </div>

                <!-- CENTER: Desktop Navigation -->
                <div class="hidden sm:flex sm:items-center sm:ml-6 space-x-8">
                    <template
                        v-for="(item, index) in frontendMenus"
                        :key="index"
                    >
                        <NavLink
                            :href="route(item.route)"
                            :active="item.active"
                        >
                            {{ $t(item.label) }}
                        </NavLink>
                    </template>
                </div>

                <!-- RIGHT: Login/Register -->
                <div class="flex items-center gap-4">
                    <template v-if="page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="text-sm font-semibold leading-6 text-text-main"
                        >
                            {{ $t("Dashboard") }}
                            <span aria-hidden="true">&rarr;</span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-semibold leading-6 text-text-main hover:text-primary transition"
                        >
                            {{ $t("Log in") }}
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition"
                        >
                            {{ $t("Register") }}
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </nav>
</template>
