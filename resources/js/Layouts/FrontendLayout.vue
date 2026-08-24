<script setup>
import { computed, ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import FrontendNavbar from "@/Layouts/Partials/FrontendNavbar.vue";
import FrontendSidebar from "@/Layouts/Partials/FrontendSidebar.vue";
import PublicFooter from "@/Layouts/Partials/PublicFooter.vue";
import PublicTopbarUtilities from "@/Layouts/Partials/PublicTopbarUtilities.vue";
import SystemToast from "@/Layouts/Partials/SystemToast.vue";
import WhatsAppFloat from "@/Components/WhatsAppFloat.vue";

defineProps({
    title: String,
});

const page = usePage();
const isSidebarOpen = ref(false);

const organization = computed(() => page.props.organization || {});
const contact = computed(() => organization.value.contact || {});
const whatsappHref = computed(() =>
    contact.value.whatsapp_e164
        ? `https://wa.me/${contact.value.whatsapp_e164}`
        : null,
);
</script>

<template>
    <div
        class="min-h-screen bg-background text-text-main font-sans selection:bg-primary selection:text-white flex flex-col"
    >
        <Head :title="title" />
        <SystemToast />

        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:px-4 focus:py-2 focus:bg-surface focus:text-text-main focus:rounded-md focus:ring-2 focus:ring-focus focus:shadow-lg"
        >
            {{ $t("Skip to main content") }}
        </a>

        <header
            class="bg-primary-dark text-white px-4 sm:px-6 lg:px-8 min-h-12 flex items-center justify-between text-xs sm:text-sm"
        >
            <div class="flex items-center gap-3 min-w-0">
                <a
                    v-if="whatsappHref"
                    :href="whatsappHref"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center min-h-control px-1 hover:text-secondary transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-sm"
                    :aria-label="$t('Chat via WhatsApp')"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                </a>
                <a
                    v-if="contact.email"
                    :href="'mailto:' + contact.email"
                    class="hidden sm:inline-flex items-center gap-1.5 text-white/80 hover:text-white transition min-h-control focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-sm truncate"
                >
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>{{ contact.email }}</span>
                </a>
            </div>

            <PublicTopbarUtilities variant="bar" />
        </header>

        <FrontendNavbar
            :sidebar-open="isSidebarOpen"
            @toggleSidebar="isSidebarOpen = !isSidebarOpen"
        />

        <FrontendSidebar :open="isSidebarOpen" @close="isSidebarOpen = false" />

        <main id="main-content" class="flex-grow" tabindex="-1">
            <slot />
        </main>

        <PublicFooter />

        <WhatsAppFloat />
    </div>
</template>
