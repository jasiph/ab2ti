<script setup>
import { Link } from "@inertiajs/vue3";
import ApplicationLogo from "./ApplicationLogo.vue";
</script>

<template>
    <Link
        :href="route('welcome')"
        class="flex items-center gap-2.5 min-w-0 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
        :aria-label="$t('Home')"
    >
        <ApplicationLogo class="block h-10 w-auto fill-current text-primary" />
        <div class="flex flex-col leading-tight">
            <span class="text-xs font-bold text-primary tracking-wide">
                Asosiasi Bank Benih
                <span class="text-secondary font-black text-sm">&amp;</span>
            </span>
            <span class="text-[10px] font-semibold text-text-muted tracking-[0.08em] uppercase">
                Teknologi Tani Indonesia
            </span>
        </div>
    </Link>
</template>
