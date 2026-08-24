<script setup>
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

defineProps({
    referenceCode: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <FrontendLayout :title="$t('Application submitted')">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <h1 class="text-2xl sm:text-3xl font-bold text-text-main leading-tight">
                {{ $t("Application submitted") }}
            </h1>
            <p class="mt-4 text-base text-text-muted leading-relaxed">
                {{ $t("We received your membership application. Staff will review it.") }}
            </p>
            <p class="mt-3 text-base text-text-muted leading-relaxed">
                {{ $t("Current application state awaiting review") }}
            </p>
            <p class="mt-3 text-base text-text-muted leading-relaxed">
                {{ $t("Submitting is not membership approval") }}
            </p>

            <div
                v-if="referenceCode"
                class="mt-6 rounded-lg border border-border bg-surface p-4"
            >
                <p class="text-sm text-text-muted">
                    {{ $t("Keep this reference code for support") }}
                </p>
                <p
                    class="mt-1 text-lg font-semibold tracking-wide text-text-main break-all"
                    aria-live="polite"
                >
                    {{ referenceCode }}
                </p>
                <p class="mt-2 text-sm text-text-muted">
                    {{ $t("Reference code is not a login or status tracker") }}
                </p>
            </div>

            <p v-else class="mt-6 text-sm text-text-muted">
                {{ $t("If you just submitted, your reference code was shown once after sending.") }}
            </p>

            <p class="mt-6 text-sm text-text-muted leading-relaxed">
                {{ $t("What happens next after submit") }}
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <BaseButton :href="route('membership.index')" variant="secondary">
                    {{ $t("Back to membership information") }}
                </BaseButton>
            </div>
        </div>
    </FrontendLayout>
</template>
