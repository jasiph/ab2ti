<script setup>
import NavLink from "@/Components/NavLink.vue";
import { useNavigation } from "@/Composables/useNavigation";
import ApplicationLogoWithText from "@/Components/ApplicationLogoWithText.vue";

defineProps({
    sidebarOpen: Boolean,
});

defineEmits(["toggleSidebar"]);

const { frontendMenus } = useNavigation();
</script>

<template>
    <nav
        class="bg-surface/90 backdrop-blur-md border-b border-border sticky top-0 z-30"
        :aria-label="$t('Navigasi')"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 gap-4">
                <div class="flex items-center min-w-0">
                    <div class="-ml-2 mr-2 flex items-center xl:hidden">
                        <button
                            type="button"
                            :aria-expanded="sidebarOpen ? 'true' : 'false'"
                            aria-controls="frontend-sidebar"
                            @click="$emit('toggleSidebar')"
                            class="inline-flex items-center justify-center min-h-control min-w-[2.75rem] p-2 rounded-md text-text-muted hover:text-text-main hover:bg-surface-variant focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus"
                        >
                            <span class="sr-only">{{
                                sidebarOpen ? $t("Close sidebar") : $t("Open main menu")
                            }}</span>
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
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

                    <div class="shrink-0 flex items-center min-w-0">
                        <ApplicationLogoWithText />
                    </div>
                </div>

                <div class="hidden xl:flex xl:items-center xl:ml-4 gap-5 min-w-0">
                    <NavLink
                        v-for="item in frontendMenus"
                        :key="item.label"
                        :href="route(item.route, item.params || {})"
                        :active="item.active"
                    >
                        {{ $t(item.label) }}
                    </NavLink>
                </div>
            </div>
        </div>
    </nav>
</template>
