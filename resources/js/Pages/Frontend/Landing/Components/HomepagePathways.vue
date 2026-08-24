<script setup>
import { Link } from "@inertiajs/vue3";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";

const pathways = [
    {
        titleKey: "About AB2TI",
        bodyKey: "Pathway about help",
        routeName: "about",
    },
    {
        titleKey: "Publications",
        bodyKey: "Pathway publications help",
        routeName: "publications.index",
    },
    {
        titleKey: "Teknologi Tani",
        bodyKey: "Pathway knowledge help",
        routeName: "knowledge.index",
    },
    {
        titleKey: "Lapak Anggota",
        bodyKey: "Pathway lapak help",
        routeName: "lapak.index",
    },
    {
        titleKey: "Membership",
        bodyKey: "Pathway membership help",
        routeName: "membership.index",
    },
];
</script>

<template>
    <PublicSection tone="muted">
        <PublicContainer>
            <SectionHeading
                :title="$t('What you can find at AB2TI')"
                :description="$t('Homepage orientation intro')"
            />

            <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <li v-for="item in pathways" :key="item.routeName">
                    <Link
                        :href="route(item.routeName)"
                        class="block h-full rounded-xl border border-border bg-surface p-5 hover:border-primary/40 hover:bg-surface-variant/60 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    >
                        <h3 class="text-lg font-semibold text-text-main">
                            {{ $t(item.titleKey) }}
                        </h3>
                        <p class="mt-2 text-sm text-text-muted leading-relaxed">
                            {{ $t(item.bodyKey) }}
                        </p>
                    </Link>
                </li>
            </ul>
        </PublicContainer>
    </PublicSection>
</template>
