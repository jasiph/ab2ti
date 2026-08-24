<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import PublicationCard from "@/Components/Publishing/PublicationCard.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicArchiveSearch from "@/Components/Public/PublicArchiveSearch.vue";

const props = defineProps({
    publications: Object,
    activeType: {
        type: String,
        default: null,
    },
    typeOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ q: "" }),
    },
});

const heading = computed(() => {
    if (!props.activeType) {
        return trans("Publications");
    }

    const match = props.typeOptions.find((item) => item.value === props.activeType);

    return match?.label || props.activeType;
});

const q = computed(() => props.filters?.q || "");

const formAction = computed(() => {
    if (!props.activeType) {
        return route("publications.index");
    }

    const match = props.typeOptions.find((item) => item.value === props.activeType);

    return route("publications.type", match?.path || props.activeType);
});

const withQuery = (href) => {
    if (!q.value) {
        return href;
    }

    const glue = href.includes("?") ? "&" : "?";

    return `${href}${glue}q=${encodeURIComponent(q.value)}`;
};

const hasResults = computed(() => (props.publications?.data?.length || 0) > 0);
</script>

<template>
    <FrontendLayout :title="heading">
        <PublicContainer class="py-10 sm:py-14">
            <p class="text-sm font-medium text-primary mb-2">{{ $t("Publications") }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold text-text-main leading-tight">
                {{ heading }}
            </h1>
            <p class="mt-3 max-w-2xl text-base text-text-muted leading-relaxed">
                {{ $t("Publications intro") }}
            </p>

            <PublicArchiveSearch
                :action="formAction"
                :q="q"
                :label="$t('Search publications')"
                :submit-label="$t('Search')"
                :clear-href="formAction"
                :clear-label="$t('Clear search')"
            />

            <nav class="mt-6 flex flex-wrap gap-2" :aria-label="$t('Publication types')">
                <Link
                    :href="withQuery(route('publications.index'))"
                    class="px-3 py-2 rounded-full text-sm border min-h-control inline-flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :class="
                        !activeType
                            ? 'bg-primary text-white border-primary'
                            : 'bg-surface text-text-main border-border hover:bg-surface-variant'
                    "
                >
                    {{ $t("All publications") }}
                </Link>
                <Link
                    v-for="item in typeOptions"
                    :key="item.value"
                    :href="withQuery(route('publications.type', item.path))"
                    class="px-3 py-2 rounded-full text-sm border min-h-control inline-flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :class="
                        activeType === item.value
                            ? 'bg-primary text-white border-primary'
                            : 'bg-surface text-text-main border-border hover:bg-surface-variant'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>

            <h2 class="sr-only">{{ $t("Publication list") }}</h2>

            <div
                v-if="hasResults"
                class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <PublicationCard
                    v-for="item in publications.data"
                    :key="item.id"
                    :publication="item"
                />
            </div>
            <p v-else class="mt-10 text-text-muted leading-relaxed max-w-2xl">
                {{ q ? $t("No publications match search") : $t("No publications yet") }}
            </p>

            <BasePagination v-if="publications.links" :links="publications.links" />
        </PublicContainer>
    </FrontendLayout>
</template>
