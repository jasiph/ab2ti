<script setup>
import { onMounted, onUnmounted, watch } from "vue";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import BaseModal from "@/Components/Base/BaseModal.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";
import PublicTopbarUtilities from "@/Layouts/Partials/PublicTopbarUtilities.vue";
import { useNavigation } from "@/Composables/useNavigation";

const props = defineProps({
    open: Boolean,
});

const emit = defineEmits(["close"]);

const { frontendMenus } = useNavigation();

const closeIfDesktop = () => {
    if (typeof window !== "undefined" && window.matchMedia("(min-width: 1280px)").matches) {
        emit("close");
    }
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            closeIfDesktop();
        }
    },
);

onMounted(() => window.addEventListener("resize", closeIfDesktop));
onUnmounted(() => window.removeEventListener("resize", closeIfDesktop));
</script>

<template>
    <BaseModal
        :show="open"
        closeable
        labelled-by="public-mobile-nav-title"
        :aria-label="$t('Navigasi')"
        max-width="sm"
        @close="emit('close')"
    >
        <div id="frontend-sidebar" class="flex h-full flex-col px-6 pb-8 pt-16">
            <div class="flex items-center gap-2 mb-6">
                <ApplicationLogo class="h-8 w-auto fill-current text-primary" />
                <span class="font-bold text-lg text-primary">AB2TI</span>
            </div>

            <h2 id="public-mobile-nav-title" class="sr-only">
                {{ $t("Navigasi") }}
            </h2>

            <nav class="flex flex-1 flex-col" :aria-label="$t('Navigasi')">
                <ul role="list" class="space-y-1">
                    <li v-for="item in frontendMenus" :key="item.label">
                        <ResponsiveNavLink
                            tone="public"
                            :href="route(item.route, item.params || {})"
                            :active="item.active"
                            @click="emit('close')"
                        >
                            {{ $t(item.label) }}
                        </ResponsiveNavLink>
                    </li>
                </ul>
            </nav>

            <div class="mt-8 pt-6 border-t border-border">
                <p class="mb-4 text-xs font-medium uppercase tracking-wide text-text-muted">
                    {{ $t("Site utilities") }}
                </p>
                <PublicTopbarUtilities variant="menu" @navigate="emit('close')" />
            </div>
        </div>
    </BaseModal>
</template>
