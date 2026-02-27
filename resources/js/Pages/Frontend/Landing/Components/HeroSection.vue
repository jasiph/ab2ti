<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';

const props = defineProps({
    hero: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        default: 'AB2TI'
    },
    subtitle: {
        type: String,
        default: 'Bersama membangun kedaulatan benih dan teknologi pertanian Indonesia.'
    },
    ctaText: {
        type: String,
        default: 'Bergabung Sekarang'
    },
    ctaLink: {
        type: String,
        default: '/register'
    },
    backgroundImage: {
        type: String,
        default: 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80'
    }
});

// Use hero data if available, otherwise fallback to props
const displayTitle = computed(() => props.hero?.title || props.title);
const displayPrimary = computed(() => props.hero?.primary_text || 'Dari Petani, Oleh Petani, Untuk Negeri');
const displaySecondary = computed(() => props.hero?.secondary_text || props.subtitle);
const displayCtaText = computed(() => props.hero?.button_text || props.ctaText);
const displayCtaLink = computed(() => props.hero?.button_url || props.ctaLink);

// Background Slider Logic
const currentImageIndex = ref(0);
const images = computed(() => {
    if (props.hero?.images && props.hero.images.length > 0) {
        return props.hero.images;
    }
    return [props.backgroundImage];
});

let intervalId = null;

const startSlider = () => {
    if (images.value.length > 1) {
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
    <div class="relative w-full h-[600px] flex items-center justify-center overflow-hidden">
        <!-- Background Image Slider -->
        <div class="absolute inset-0 z-0">
             <transition-group name="fade" tag="div" class="w-full h-full relative">
                <div
                    v-for="(img, index) in images"
                    :key="img"
                    v-show="currentImageIndex === index"
                    class="absolute inset-0 w-full h-full"
                >
                    <img :src="img" alt="Hero Background" class="w-full h-full object-cover transition-transform duration-[10000ms] ease-linear transform scale-100 hover:scale-110" />
                    <div class="absolute inset-0 bg-primary-dark/60 mix-blend-multiply"></div>
                </div>
             </transition-group>
        </div>

        <!-- Content -->
        <div class="relative z-10 text-center px-4 max-w-4xl mx-auto flex flex-col items-center justify-center h-full pt-20">
             <!-- Title (Small Header) -->
            <div
                v-if="displayTitle"
                class="text-sm md:text-base font-semibold tracking-[0.2em] uppercase text-secondary mb-4 animate-fade-in-up"
            >
                {{ displayTitle }}
            </div>

            <!-- Primary Text (Main Heading) -->
            <h1
                v-if="displayPrimary"
                class="text-4xl md:text-6xl lg:text-7xl font-bold text-white mb-6 tracking-tight drop-shadow-lg leading-tight animate-fade-in-up delay-100"
            >
                {{ displayPrimary }}
            </h1>

            <!-- Secondary Text (Medium Description) -->
            <p
                v-if="displaySecondary"
                class="text-lg md:text-2xl text-stone-200 mb-10 font-light max-w-2xl mx-auto leading-relaxed animate-fade-in-up delay-200"
            >
                {{ displaySecondary }}
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in-up delay-300" v-if="displayCtaText || displayCtaLink">
                <Link :href="displayCtaLink" class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold text-primary-dark bg-secondary hover:bg-yellow-300 rounded-full transition duration-300 shadow-[0_0_20px_rgba(202,155,30,0.5)] hover:shadow-[0_0_30px_rgba(202,155,30,0.7)] transform hover:-translate-y-1">
                    {{ displayCtaText }}
                </Link>
                <a href="#program" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-white border-2 border-white/30 hover:bg-white/10 rounded-full transition duration-300 backdrop-blur-sm">
                    {{ $t('Program Kami') }}
                </a>
            </div>
        </div>

        <!-- Decorative Curve Bottom -->
        <div class="absolute bottom-0 left-0 right-0 z-20">
            <svg class="fill-background w-full h-12 md:h-24" preserveAspectRatio="none" viewBox="0 0 1440 320">
                <path fill-opacity="1" d="M0,224L80,213.3C160,203,320,181,480,181.3C640,181,800,203,960,202.7C1120,203,1280,181,1360,170.7L1440,160L1440,320L1360,320C1280,320,1120,320,960,320C800,320,640,320,480,320C320,320,160,320,80,320L0,320Z"></path>
            </svg>
        </div>
    </div>
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
</style>
