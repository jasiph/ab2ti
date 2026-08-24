<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import PublicLapakListingCard from "@/Components/Lapak/PublicLapakListingCard.vue";
import LapakContactActions from "@/Components/Lapak/LapakContactActions.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";

defineProps({
    lapak: {
        type: Object,
        required: true,
    },
    listings: Object,
});
</script>

<template>
    <FrontendLayout :title="lapak.name">
        <PublicContainer class="py-10 sm:py-14">
            <nav class="text-sm text-text-muted" :aria-label="$t('Breadcrumb')">
                <Link
                    :href="route('lapak.index')"
                    class="hover:text-primary focus:outline-none focus-visible:underline"
                >
                    {{ $t("Lapak Anggota") }}
                </Link>
            </nav>

            <p class="mt-4 text-sm font-medium text-primary">{{ $t("AB2TI member storefront") }}</p>
            <div class="mt-2 grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <div class="lg:col-span-2 min-w-0">
                    <h1 class="text-3xl sm:text-4xl font-bold text-text-main leading-tight break-words">
                        {{ lapak.name }}
                    </h1>
                    <p v-if="lapak.location_text" class="mt-2 text-sm text-text-muted">
                        {{ lapak.location_text }}
                    </p>
                    <p v-if="lapak.summary" class="mt-4 text-lg text-text-muted leading-relaxed">
                        {{ lapak.summary }}
                    </p>
                    <p
                        v-if="lapak.description"
                        class="mt-4 text-base text-text-main leading-relaxed whitespace-pre-wrap break-words"
                    >
                        {{ lapak.description }}
                    </p>
                </div>
                <div v-if="lapak.cover_url" class="rounded-xl overflow-hidden border border-border bg-surface-variant">
                    <img
                        :src="lapak.cover_url"
                        :alt="lapak.cover_alt || lapak.name"
                        class="w-full h-auto object-cover aspect-[4/3]"
                    />
                </div>
            </div>

            <div class="mt-8">
                <LapakContactActions
                    :channels="lapak.contact || []"
                    :heading="$t('Contact seller')"
                    :empty-label="$t('No public seller contact')"
                />
                <p class="mt-3 text-sm text-text-muted leading-relaxed">
                    {{ $t("Seller transaction notice") }}
                </p>
            </div>

            <h2 class="mt-12 text-2xl font-semibold text-text-main">
                {{ $t("Storefront listings") }}
            </h2>

            <div
                v-if="listings?.data?.length"
                class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <PublicLapakListingCard
                    v-for="item in listings.data"
                    :key="item.url"
                    :listing="item"
                />
            </div>
            <p v-else class="mt-6 text-text-muted leading-relaxed max-w-2xl">
                {{ $t("Storefront has no listings") }}
            </p>

            <BasePagination v-if="listings?.links" :links="listings.links" />
        </PublicContainer>
    </FrontendLayout>
</template>
