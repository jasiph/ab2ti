<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import PublicLapakListingCard from "@/Components/Lapak/PublicLapakListingCard.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicArchiveSearch from "@/Components/Public/PublicArchiveSearch.vue";

const props = defineProps({
    listings: Object,
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ q: "", kategori: null }),
    },
});

const q = computed(() => props.filters?.q || "");
const kategori = computed(() => props.filters?.kategori || "");
const hasResults = computed(() => (props.listings?.data?.length || 0) > 0);
const hasFilter = computed(() => Boolean(q.value || kategori.value));

const extraFields = computed(() => {
    if (!kategori.value) {
        return [];
    }

    return [{ name: "kategori", value: kategori.value }];
});

const withQuery = (href) => {
    if (!q.value) {
        return href;
    }

    const glue = href.includes("?") ? "&" : "?";

    return `${href}${glue}q=${encodeURIComponent(q.value)}`;
};

const categoryHref = (slug) => {
    const params = new URLSearchParams();

    if (q.value) {
        params.set("q", q.value);
    }

    if (slug) {
        params.set("kategori", slug);
    }

    const query = params.toString();

    return query ? `${route("lapak.index")}?${query}` : route("lapak.index");
};
</script>

<template>
    <FrontendLayout :title="$t('Lapak Anggota')">
        <PublicContainer class="py-10 sm:py-14">
            <p class="text-sm font-medium text-primary mb-2">{{ $t("Lapak Anggota") }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold text-text-main leading-tight">
                {{ $t("Lapak Anggota") }}
            </h1>
            <p class="mt-3 max-w-2xl text-base text-text-muted leading-relaxed">
                {{ $t("Lapak intro") }}
            </p>

            <PublicArchiveSearch
                :action="route('lapak.index')"
                :q="q"
                :label="$t('Search lapak listings')"
                :submit-label="$t('Search')"
                :clear-href="route('lapak.index')"
                :clear-label="$t('Clear search')"
                :extra-fields="extraFields"
            />

            <nav class="mt-6 flex flex-wrap gap-2" :aria-label="$t('Lapak categories')">
                <Link
                    :href="withQuery(route('lapak.index'))"
                    class="px-3 py-2 rounded-full text-sm border min-h-control inline-flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :aria-current="!kategori ? 'page' : undefined"
                    :class="
                        !kategori
                            ? 'bg-primary text-white border-primary'
                            : 'bg-surface text-text-main border-border hover:bg-surface-variant'
                    "
                >
                    {{ $t("All categories") }}
                </Link>
                <Link
                    v-for="item in categories"
                    :key="item.slug"
                    :href="categoryHref(item.slug)"
                    class="px-3 py-2 rounded-full text-sm border min-h-control inline-flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :aria-current="kategori === item.slug ? 'page' : undefined"
                    :class="
                        kategori === item.slug
                            ? 'bg-primary text-white border-primary'
                            : 'bg-surface text-text-main border-border hover:bg-surface-variant'
                    "
                >
                    {{ item.name }}
                </Link>
            </nav>

            <h2 class="sr-only">{{ $t("Lapak listing list") }}</h2>

            <div
                v-if="hasResults"
                class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <PublicLapakListingCard
                    v-for="item in listings.data"
                    :key="item.url"
                    :listing="item"
                />
            </div>
            <div v-else class="mt-10 text-text-muted leading-relaxed max-w-2xl space-y-3">
                <p v-if="hasFilter">
                    {{ $t("No lapak listings match search") }}
                </p>
                <p v-else>
                    {{ $t("No lapak listings yet") }}
                </p>
                <p v-if="q">{{ $t("Active search") }}: {{ q }}</p>
                <p v-if="kategori">{{ $t("Active category") }}: {{ kategori }}</p>
                <p v-if="hasFilter">
                    <Link
                        :href="route('lapak.index')"
                        class="text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    >
                        {{ $t("Clear search") }}
                    </Link>
                </p>
            </div>

            <BasePagination v-if="listings.links" :links="listings.links" />
        </PublicContainer>
    </FrontendLayout>
</template>
