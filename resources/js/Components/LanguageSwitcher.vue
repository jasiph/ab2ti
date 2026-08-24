<script setup>
import { computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import { loadLanguageAsync } from "laravel-vue-i18n";

const page = usePage();
// Gunakan computed agar reaktif terhadap perubahan props Inertia
const currentLocale = computed(() => page.props.locale);

const switchLanguage = (lang) => {
    // 1. Visit endpoint laravel untuk set session
    router.get(
        `/language/${lang}`,
        {},
        {
            preserveState: true, // Opsional: jaga state komponen lain
            preserveScroll: true,
            onSuccess: () => {
                loadLanguageAsync(lang);
            },
        },
    );
};
</script>

<template>
    <div class="flex items-center text-sm font-medium">
        <!-- Tampilkan tombol EN jika locale saat ini ID -->
        <button
            v-if="currentLocale === 'id'"
            @click.prevent="switchLanguage('en')"
            class="text-text-muted hover:text-primary transition flex items-center group min-h-control px-1 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            :aria-label="$t('Switch to English')"
            :title="$t('Switch to English')"
        >
            <span
                class="mr-1 text-[10px] opacity-70 group-hover:opacity-100 transition"
                >{{ $t("Change to") }}</span
            >
            <span
                class="text-sm space-x-8 sm:-my-px font-bold border-b-2 border-transparent group-hover:border-primary transition"
                >EN</span
            >
        </button>

        <!-- Tampilkan tombol ID jika locale saat ini EN (atau lainnya) -->
        <button
            v-else
            @click.prevent="switchLanguage('id')"
            class="text-text-muted hover:text-primary transition flex items-center group min-h-control px-1 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            :aria-label="$t('Switch to Indonesian')"
            :title="$t('Switch to Indonesian')"
        >
            <span
                class="mr-1 text-[10px] opacity-70 group-hover:opacity-100 transition"
                >{{ $t("Change to") }}</span
            >
            <span
                class="text-sm font-bold border-b-2 border-transparent group-hover:border-primary transition"
                >ID</span
            >
        </button>
    </div>
</template>
