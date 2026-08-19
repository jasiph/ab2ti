<script setup>
import { Link } from "@inertiajs/vue3";
import ApplicationMark from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";
import { useNavigation } from "@/Composables/useNavigation"; // <--- 1. Import Composable
import { computed } from "vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

defineEmits(["toggleSidebar"]);

// 2. Ambil Data Menu dari Composable
const { menus } = useNavigation();

const props = defineProps({
    type: {
        type: String,
        default: "frontend",
    },
});

const href = computed(() => {
    return props.type === "frontend" ? "/dashboard" : "/";
});
</script>

<template>
    <header
        class="bg-surface-variant w-full px-4 sm:px-6 lg:px-8 flex items-center justify-between h-10 border-b border-border text-xs"
    >
        <div class="flex items-center space-x-4 text-text-muted">
            <span class="font-bold text-primary">AB2TI 2.0</span>
            <span class="hidden sm:inline">Support</span>
            <span class="hidden sm:inline">Docs</span>
        </div>

        <div class="flex items-center space-x-3">
            <BaseButton variant="ghost" size="sm" :href="href">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    class="w-5 h-5 fill-white"
                >
                     http://localhost:8081/
                    <path
                        fill="currentColor"
                        fill-rule="evenodd"
                        d="M5 5h10v4h4v10H9v-4H5zm6 6v6h6v-6z"
                        clip-rule="evenodd"
                    />
                </svg>
            </BaseButton>
            <ThemeToggle />
            <div class="h-4 w-px bg-border"></div>
            <LanguageSwitcher />
        </div>
    </header>

    <nav class="bg-surface border-b border-border sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="shrink-0 flex items-center">
                        <Link :href="route('dashboard')">
                            <ApplicationMark
                                class="block h-9 w-auto fill-primary"
                            />
                        </Link>
                    </div>

                    <div
                        class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex items-center"
                    >
                        <template v-for="(item, index) in menus" :key="index">
                            <NavLink
                                v-if="!item.children"
                                :href="route(item.route)"
                                :active="item.active"
                            >
                                <svg
                                    v-if="item.icon"
                                    class="w-4 h-4 mr-2 opacity-70"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        :d="item.icon"
                                    />
                                </svg>
                                {{ item.label }}
                            </NavLink>

                            <div
                                v-else
                                class="relative h-full flex items-center"
                            >
                                <Dropdown align="left" width="48">
                                    <template #trigger>
                                        <button
                                            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                                            :class="
                                                item.active
                                                    ? 'border-primary text-text-main'
                                                    : 'border-transparent text-text-muted hover:text-text-main hover:border-border'
                                            "
                                        >
                                            <svg
                                                v-if="item.icon"
                                                class="w-4 h-4 mr-2 opacity-70"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    :d="item.icon"
                                                />
                                            </svg>

                                            {{ item.label }}

                                            <svg
                                                class="ml-1 -mr-0.5 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <template
                                            v-for="(
                                                child, cIndex
                                            ) in item.children"
                                            :key="cIndex"
                                        >
                                            <DropdownLink
                                                :href="route(child.route)"
                                            >
                                                <span
                                                    :class="{
                                                        'font-bold text-primary':
                                                            child.active,
                                                    }"
                                                >
                                                    {{ child.label }}
                                                </span>
                                            </DropdownLink>
                                        </template>
                                    </template>
                                </Dropdown>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <div class="ms-3 relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    v-if="
                                        $page.props.auth.user.profile_photo_url
                                    "
                                    class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition"
                                >
                                    <img
                                        class="h-8 w-8 rounded-full object-cover"
                                        :src="
                                            $page.props.auth.user
                                                .profile_photo_url
                                        "
                                        :alt="$page.props.auth.user.name"
                                    />
                                </button>
                                <span v-else class="inline-flex rounded-md">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-text-muted bg-surface hover:text-text-main focus:outline-none transition"
                                    >
                                        {{ $page.props.auth.user.name }}
                                        <svg
                                            class="ms-2 -me-0.5 h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                                            />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            <template #content>
                                <div
                                    class="block px-4 py-2 text-xs text-text-muted"
                                >
                                    {{ $t("Manage Account") }}
                                </div>
                                <DropdownLink :href="route('profile.show')">
                                    {{ $t("Profile") }}
                                </DropdownLink>
                                <div class="border-t border-border"></div>
                                <form
                                    @submit.prevent="
                                        $inertia.post(route('logout'))
                                    "
                                >
                                    <DropdownLink as="button">
                                        {{ $t("Log Out") }}
                                    </DropdownLink>
                                </form>
                            </template>
                        </Dropdown>
                    </div>
                </div>

                <div class="-me-2 flex items-center sm:hidden">
                    <button
                        @click="$emit('toggleSidebar')"
                        class="inline-flex items-center justify-center p-2 rounded-md text-text-muted hover:text-text-main hover:bg-surface-variant focus:outline-none"
                    >
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
            </div>
        </div>
    </nav>
</template>
