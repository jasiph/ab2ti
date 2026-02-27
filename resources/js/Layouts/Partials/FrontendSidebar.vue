<script setup>
import { Link } from "@inertiajs/vue3";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";
import { useNavigation } from "@/Composables/useNavigation";

defineProps({
    open: Boolean,
});

defineEmits(["close"]);

const { frontendMenus } = useNavigation();
</script>

<template>
    <!-- Off-canvas Sidebar for Mobile -->
    <div v-show="open" class="relative z-50 sm:hidden" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div
            v-show="open"
            class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity ease-linear duration-300"
            enter-active-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="opacity-100"
            leave-to-class="opacity-0"
            @click="$emit('close')"
        ></div>

        <div class="fixed inset-0 flex">
            <!-- Sidebar Panel -->
            <div
                v-show="open"
                class="relative mr-16 flex w-full max-w-xs flex-1 transform transition ease-in-out duration-300"
                enter-active-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                    <button type="button" class="-m-2.5 p-2.5" @click="$emit('close')">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Sidebar Content -->
                <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-surface px-6 pb-4 ring-1 ring-white/10">
                    <div class="flex h-16 shrink-0 items-center gap-2">
                        <ApplicationLogo class="h-8 w-auto fill-current text-primary" />
                        <span class="font-bold text-lg text-primary">AB2TI</span>
                    </div>
                    
                    <nav class="flex flex-1 flex-col">
                        <ul role="list" class="flex flex-1 flex-col gap-y-7">
                            <li>
                                <ul role="list" class="-mx-2 space-y-1">
                                    <li v-for="item in frontendMenus" :key="item.label">
                                        <ResponsiveNavLink
                                            :href="route(item.route)"
                                            :active="item.active"
                                            @click="$emit('close')"
                                        >
                                            {{ item.label }}
                                        </ResponsiveNavLink>
                                    </li>
                                </ul>
                            </li>
                            
                            <li class="mt-auto">
                                <Link
                                    :href="route('login')"
                                    class="group -mx-2 flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-text-muted hover:bg-surface-variant hover:text-text-main"
                                >
                                    Log in
                                </Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>
