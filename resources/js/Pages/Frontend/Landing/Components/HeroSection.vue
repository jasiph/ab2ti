<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import BaseButton from "@/Components/Base/BaseButton.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";

const props = defineProps({
    hero: {
        type: Object,
        default: null,
    },
});

const displayEyebrow = computed(() => props.hero?.title || trans("AB2TI"));
const displayPrimary = computed(
    () => props.hero?.primary_text || trans("Hero fallback primary"),
);
const displaySecondary = computed(
    () => props.hero?.secondary_text || trans("Hero fallback secondary"),
);
const displayCtaText = computed(
    () => props.hero?.button_text || trans("Apply for membership"),
);
const displayCtaLink = computed(
    () => props.hero?.button_url || route("membership.index"),
);

const currentImageIndex = ref(0);
const images = computed(() => {
    if (props.hero?.images && props.hero.images.length > 0) {
        return props.hero.images;
    }

    return [];
});

let intervalId = null;

const prefersReducedMotion = () =>
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const startSlider = () => {
    if (images.value.length > 1 && !prefersReducedMotion()) {
        intervalId = setInterval(() => {
            currentImageIndex.value = (currentImageIndex.value + 1) % images.value.length;
        }, 5000);
    }
};

const stopSlider = () => {
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

onMounted(() => {
    startSlider();
});

onUnmounted(() => {
    stopSlider();
});

watch(images, () => {
    stopSlider();
    currentImageIndex.value = 0;
    startSlider();
});
</script>

<template>
    <section
        class="relative w-full min-h-[28rem] sm:min-h-[32rem] md:min-h-[36rem] flex items-center justify-center overflow-hidden bg-primary-dark"
        aria-labelledby="homepage-hero-heading"
    >
        <div v-if="images.length" class="absolute inset-0 z-0" aria-hidden="true">
            <transition-group name="fade" tag="div" class="w-full h-full relative">
                <div
                    v-for="(img, index) in images"
                    :key="img"
                    v-show="currentImageIndex === index"
                    class="absolute inset-0 w-full h-full"
                >
                    <img
                        :src="img"
                        alt=""
                        :loading="index === 0 ? 'eager' : 'lazy'"
                        :fetchpriority="index === 0 ? 'high' : 'low'"
                        class="w-full h-full object-cover object-center"
                    />
                    <div class="absolute inset-0 bg-primary-dark/60 mix-blend-multiply" />
                </div>
            </transition-group>
        </div>
        <div
            v-else
            class="absolute inset-0 z-0 bg-gradient-to-br from-primary to-primary-dark"
            aria-hidden="true"
        />

        <PublicContainer class="relative z-10 py-16 sm:py-20">
            <div class="max-w-3xl mx-auto text-center flex flex-col items-center">
                <p
                    v-if="displayEyebrow"
                    class="text-sm md:text-base font-semibold tracking-[0.2em] uppercase text-secondary mb-4"
                >
                    {{ displayEyebrow }}
                </p>

                <h1
                    id="homepage-hero-heading"
                    class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 tracking-tight leading-tight"
                >
                    {{ displayPrimary }}
                </h1>

                <p
                    v-if="displaySecondary"
                    class="text-base sm:text-lg md:text-xl text-white/90 mb-10 font-normal max-w-2xl mx-auto leading-relaxed"
                >
                    {{ displaySecondary }}
                </p>

                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center w-full sm:w-auto">
                    <BaseButton
                        :href="displayCtaLink"
                        size="lg"
                        class="!bg-secondary !text-primary-dark hover:!brightness-95"
                    >
                        {{ displayCtaText }}
                    </BaseButton>
                    <BaseButton
                        :href="route('knowledge.index')"
                        variant="outline"
                        size="lg"
                        class="!border-white !text-white hover:!bg-white/10"
                    >
                        {{ $t("Teknologi Tani") }}
                    </BaseButton>
                </div>
            </div>
        </PublicContainer>
    </section>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 1s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
