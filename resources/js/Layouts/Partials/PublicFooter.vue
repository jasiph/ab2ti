<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import ApplicationMark from "@/Components/ApplicationMark.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import { useNavigation } from "@/Composables/useNavigation";

const page = usePage();
const { frontendMenus } = useNavigation();

const organization = computed(() => page.props.organization || {});
const contact = computed(() => organization.value.contact || {});
const year = new Date().getFullYear();

const whatsappHref = computed(() =>
    contact.value.whatsapp_e164
        ? `https://wa.me/${contact.value.whatsapp_e164}`
        : null,
);

const footerLinkClass =
    "hover:text-secondary transition focus:outline-none focus-visible:text-secondary focus-visible:underline min-h-control inline-flex items-center";
</script>

<template>
    <footer class="bg-primary-dark text-white/80 pt-16 pb-8 border-t border-white/10">
        <PublicContainer>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                <div>
                    <div class="flex items-center gap-2 mb-6">
                        <ApplicationMark class="h-10 w-auto fill-white text-white" />
                        <span class="text-white font-bold text-xl">{{
                            organization.short_name || "AB2TI"
                        }}</span>
                    </div>
                    <p class="text-sm leading-relaxed text-white/70 mb-6 max-w-sm">
                        {{ $t("AB2TI public tagline") }}
                    </p>
                </div>

                <div>
                    <h2 class="text-white font-bold text-lg mb-4">{{ $t("Navigasi") }}</h2>
                    <ul class="space-y-1 text-sm">
                        <li v-for="item in frontendMenus" :key="item.label">
                            <Link
                                :href="route(item.route, item.params || {})"
                                :class="footerLinkClass"
                            >
                                {{ $t(item.label) }}
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-white font-bold text-lg mb-4">{{ $t("Contact") }}</h2>
                    <ul class="space-y-4 text-sm">
                        <li v-if="contact.address" class="flex items-start">
                            <svg
                                class="w-5 h-5 mr-3 text-secondary shrink-0 mt-0.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            <span>{{ contact.address }}</span>
                        </li>
                        <li v-if="contact.phone" class="flex items-center">
                            <svg
                                class="w-5 h-5 mr-3 text-secondary shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                />
                            </svg>
                            <a :href="'tel:' + contact.phone" :class="footerLinkClass">{{
                                contact.phone
                            }}</a>
                        </li>
                        <li v-if="contact.email" class="flex items-center">
                            <svg
                                class="w-5 h-5 mr-3 text-secondary shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>
                            <a :href="'mailto:' + contact.email" :class="footerLinkClass">{{
                                contact.email
                            }}</a>
                        </li>
                        <li v-if="whatsappHref">
                            <a
                                :href="whatsappHref"
                                target="_blank"
                                rel="noopener noreferrer"
                                :class="footerLinkClass"
                            >
                                {{ $t("Chat via WhatsApp") }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10 pt-8 text-center text-xs text-white/60">
                <p>
                    &copy; {{ year }}
                    {{ organization.name || "AB2TI" }}.
                    {{ $t("All rights reserved.") }}
                </p>
            </div>
        </PublicContainer>
    </footer>
</template>
