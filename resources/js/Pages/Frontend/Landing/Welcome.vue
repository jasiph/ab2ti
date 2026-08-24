<script setup>
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import HeroSection from "./Components/HeroSection.vue";
import WelcomeSection from "./Components/WelcomeSection.vue";
import HomepagePathways from "./Components/HomepagePathways.vue";
import LatestPublications from "./Components/LatestPublications.vue";
import LatestKnowledge from "./Components/LatestKnowledge.vue";
import LatestLapakListings from "./Components/LatestLapakListings.vue";
import MembershipInvite from "./Components/MembershipInvite.vue";

defineProps({
    hero: Object,
    welcomeSection: {
        type: Object,
        default: null,
    },
    latestPublications: {
        type: Array,
        default: () => [],
    },
    latestKnowledgeArticles: {
        type: Array,
        default: () => [],
    },
    latestLapakListings: {
        type: Array,
        default: () => [],
    },
    membershipCta: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <FrontendLayout :title="$t('Home')">
        <HeroSection :hero="hero" />

        <WelcomeSection :section="welcomeSection" />

        <HomepagePathways />

        <LatestPublications :publications="latestPublications" />

        <LatestKnowledge :articles="latestKnowledgeArticles" />

        <LatestLapakListings :listings="latestLapakListings" />

        <MembershipInvite :cta="membershipCta" />
    </FrontendLayout>
</template>
