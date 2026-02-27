<script setup>
import { Link } from "@inertiajs/vue3";
import { ref } from "vue"; // Import ref untuk state accordion
import ApplicationMark from "@/Components/ApplicationMark.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";
import { useNavigation } from "@/Composables/useNavigation"; // <--- 1. Import Composable

defineProps({
    open: Boolean, // State untuk mobile menu
});

defineEmits(["close"]); // Event untuk menutup sidebar

// 2. Ambil Data Menu
const { menus } = useNavigation();

// 3. State untuk Accordion (Dropdown di Sidebar)
const expandedMenus = ref({});

const toggleMenu = (label) => {
    expandedMenus.value[label] = !expandedMenus.value[label];
};
</script>

<template>
    <div v-show="open" class="fixed inset-0 z-40 flex sm:hidden">
        <div
            class="fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity"
            @click="$emit('close')"
        ></div>

        <div
            class="relative flex-1 flex flex-col max-w-xs w-full bg-surface border-r border-border transition-transform transform duration-300"
        >
            <div class="absolute top-0 right-0 -mr-12 pt-2">
                <button
                    @click="$emit('close')"
                    class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                >
                    <span class="sr-only">Close sidebar</span>
                    <svg
                        class="h-6 w-6 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                <div class="flex-shrink-0 flex items-center px-4 mb-5">
                    <ApplicationMark class="block h-8 w-auto fill-primary" />
                    <span class="ml-2 font-bold text-lg text-text-main"
                        >GoldenApp</span
                    >
                </div>

                <nav class="mt-5 px-2 space-y-1">
                    <template v-for="(item, index) in menus" :key="index">
                        <ResponsiveNavLink
                            v-if="!item.children"
                            :href="route(item.route)"
                            :active="item.active"
                        >
                            <span class="flex items-center">
                                <svg
                                    v-if="item.icon"
                                    class="mr-3 h-5 w-5 flex-shrink-0 opacity-70"
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
                            </span>
                        </ResponsiveNavLink>

                        <div v-else class="space-y-1">
                            <button
                                @click="toggleMenu(item.label)"
                                class="w-full flex items-center justify-between pl-3 pr-4 py-2 border-l-4 text-base font-medium transition focus:outline-none"
                                :class="
                                    item.active
                                        ? 'border-primary text-primary bg-primary/5'
                                        : 'border-transparent text-text-muted hover:text-text-main hover:bg-surface-variant hover:border-text-muted'
                                "
                            >
                                <span class="flex items-center">
                                    <svg
                                        v-if="item.icon"
                                        class="mr-3 h-5 w-5 flex-shrink-0 opacity-70"
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
                                </span>
                                <svg
                                    class="w-4 h-4 transform transition-transform duration-200"
                                    :class="
                                        expandedMenus[item.label] || item.active
                                            ? 'rotate-180'
                                            : ''
                                    "
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    ></path>
                                </svg>
                            </button>

                            <div
                                v-show="
                                    expandedMenus[item.label] || item.active
                                "
                                class="space-y-1"
                            >
                                <template
                                    v-for="(child, cIndex) in item.children"
                                    :key="cIndex"
                                >
                                    <ResponsiveNavLink
                                        :href="route(child.route)"
                                        :active="child.active"
                                        class="pl-10 text-sm"
                                    >
                                        {{ child.label }}
                                    </ResponsiveNavLink>
                                </template>
                            </div>
                        </div>
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
