<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import LapakContactActions from "@/Components/Lapak/LapakContactActions.vue";
import LapakImageGallery from "@/Components/Lapak/LapakImageGallery.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";

defineProps({
    listing: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <FrontendLayout :title="listing.title">
        <PublicContainer width="prose" class="py-10 sm:py-14">
            <nav class="text-sm text-text-muted" :aria-label="$t('Breadcrumb')">
                <Link
                    :href="route('lapak.index')"
                    class="hover:text-primary focus:outline-none focus-visible:underline"
                >
                    {{ $t("Lapak Anggota") }}
                </Link>
                <span aria-hidden="true"> / </span>
                <Link
                    v-if="listing.lapak"
                    :href="listing.lapak.url"
                    class="hover:text-primary focus:outline-none focus-visible:underline"
                >
                    {{ listing.lapak.name }}
                </Link>
            </nav>

            <article class="mt-4">
                <p v-if="listing.category_name" class="text-sm font-medium text-primary">
                    {{ listing.category_name }}
                </p>
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold text-text-main leading-tight break-words">
                    {{ listing.title }}
                </h1>
                <p class="mt-4 text-xl font-semibold text-text-main">
                    {{ listing.price_label }}
                </p>
                <p v-if="listing.lapak" class="mt-2 text-sm text-text-muted break-words">
                    {{ listing.lapak.name }}
                    <span v-if="listing.lapak.location_text"> · {{ listing.lapak.location_text }}</span>
                </p>
                <p v-if="listing.summary" class="mt-4 text-lg text-text-muted leading-relaxed">
                    {{ listing.summary }}
                </p>

                <div class="mt-8">
                    <LapakImageGallery :images="listing.images || []" :title="listing.title" />
                </div>

                <p
                    v-if="listing.description"
                    class="mt-8 text-base text-text-main leading-relaxed whitespace-pre-wrap break-words"
                >
                    {{ listing.description }}
                </p>

                <div class="mt-10">
                    <LapakContactActions
                        :channels="listing.lapak?.contact || []"
                        :heading="$t('Contact seller')"
                        :empty-label="$t('No public seller contact')"
                    />
                    <p class="mt-3 text-sm text-text-muted leading-relaxed">
                        {{ $t("Seller transaction notice") }}
                    </p>
                </div>

                <p v-if="listing.lapak" class="mt-10">
                    <Link
                        :href="listing.lapak.url"
                        class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    >
                        {{ $t("Back to storefront") }}
                    </Link>
                </p>
            </article>
        </PublicContainer>
    </FrontendLayout>
</template>
