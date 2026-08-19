<script setup>
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import HeroSection from "./Components/HeroSection.vue";
import WelcomeSection from "./Components/WelcomeSection.vue";
import ServiceEvents from "./Components/ServiceEvents.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
    hero: Object,
    welcomeSection: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <FrontendLayout title="Selamat Datang">
        <HeroSection :hero="hero" />
        
        <WelcomeSection :section="welcomeSection" />
        
        <ServiceEvents />
    </FrontendLayout>
</template>
