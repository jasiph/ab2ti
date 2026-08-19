<script setup>
import { computed } from "vue";
import { trans } from "laravel-vue-i18n";

const props = defineProps({
    section: {
        type: Object,
        default: null,
    },
});

const defaultTitle = "Tentang AB2TI";
const defaultContent =
    "Asosiasi Bank Benih dan Teknologi Tani Indonesia (AB2TI) adalah organisasi komunitas petani yang berkomitmen untuk membangun kedaulatan benih dan teknologi pertanian. Kami mengumpulkan, mengkarakterisasi, dan mengkonservasi benih-benih petani Indonesia untuk masa depan pertanian yang berkelanjutan.";
const defaultSecondary =
    "Menjadi pusat keunggulan di bidang perbenihan dan teknologi yang dimiliki oleh petani Indonesia dalam upaya meningkatkan kedaulatan petani atas benih dan teknologi, mendukung sistem pertanian berkelanjutan untuk penghidupan petani yang lebih mandiri dan sejahtera.";

const displayTitle = computed(() => props.section?.title || defaultTitle);
const displayContent = computed(() => props.section?.content || defaultContent);
const displaySecondaryContent = computed(() => props.section?.secondary_content || defaultSecondary);
const displayImage = computed(
    () =>
        props.section?.image_url ||
        "https://images.unsplash.com/photo-1574943320219-553eb213f72d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80",
);
const displayCtaLabel = computed(() => props.section?.cta_label || trans("Pelajari Lebih Lanjut"));
const displayCtaUrl = computed(() => props.section?.cta_url || "#program");
</script>

<template>
    <section class="py-16 md:py-24 bg-background">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div class="order-2 md:order-1">
                    <div class="w-16 h-1 bg-secondary mb-6"></div>
                    <h2 class="text-3xl md:text-4xl font-bold text-primary mb-6">
                        {{ displayTitle }}
                    </h2>
                    <p class="text-lg text-text-muted leading-relaxed mb-6">
                        {{ displayContent }}
                    </p>
                    <p class="text-lg text-text-muted leading-relaxed">
                        {{ displaySecondaryContent }}
                    </p>

                    <div class="mt-8">
                        <a
                            :href="displayCtaUrl"
                            class="text-primary font-semibold hover:text-primary-dark transition inline-flex items-center group"
                        >
                            {{ displayCtaLabel }}
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 ml-2 group-hover:translate-x-1 transition-transform"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="order-1 md:order-2 relative">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl aspect-[4/3] group">
                        <img :src="displayImage" alt="AB2TI" class="w-full h-full object-cover transition duration-700 group-hover:scale-110" />
                        <div class="absolute inset-0 bg-primary/10 group-hover:bg-transparent transition duration-500"></div>
                    </div>
                    <div class="absolute -bottom-6 -left-6 w-24 h-24 bg-secondary/20 rounded-full -z-10 blur-xl"></div>
                    <div class="absolute -top-6 -right-6 w-32 h-32 bg-primary/10 rounded-full -z-10 blur-xl"></div>
                </div>
            </div>
        </div>
    </section>
</template>
