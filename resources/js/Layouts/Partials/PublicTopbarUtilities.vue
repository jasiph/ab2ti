<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";

const props = defineProps({
    variant: {
        type: String,
        default: "bar",
    },
});

const emit = defineEmits(["navigate"]);

const page = usePage();
const canAccessAdmin = computed(() => page.props.auth?.can_access_admin === true);
const isLinkedMember = computed(
    () => Boolean(page.props.auth?.user) && page.props.auth?.linked_to_member === true,
);
const isGuest = computed(() => !page.props.auth?.user);

const isBar = computed(() => props.variant === "bar");
const onNavigate = () => emit("navigate");

const actionClass = computed(() =>
    isBar.value
        ? "inline-flex items-center min-h-control px-1 text-white/80 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-sm text-xs sm:text-sm font-medium"
        : "inline-flex items-center justify-center w-full min-h-control text-sm font-medium text-text-muted hover:text-text-main focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm",
);
</script>

<template>
    <div
        :class="
            isBar
                ? 'flex items-center gap-3 shrink-0'
                : 'space-y-4'
        "
        :aria-label="$t('Site utilities')"
        role="group"
    >
        <div :class="isBar ? 'flex items-center gap-3' : 'flex items-center justify-center gap-3'">
            <ThemeToggle
                :class="
                    isBar
                        ? 'text-white hover:text-secondary transition'
                        : 'text-text-muted hover:text-primary'
                "
            />
            <div
                class="h-4 w-px"
                :class="isBar ? 'bg-white/20' : 'bg-border'"
                aria-hidden="true"
            />
            <LanguageSwitcher
                :class="
                    isBar
                        ? 'text-white hover:text-secondary transition'
                        : 'text-text-muted hover:text-primary'
                "
            />
        </div>

        <template v-if="canAccessAdmin">
            <div
                v-if="isBar"
                class="h-4 w-px bg-white/20"
                aria-hidden="true"
            />
            <Link
                :href="route('admin.dashboard')"
                :class="actionClass"
                @click="onNavigate"
            >
                {{ $t("Dashboard") }}
            </Link>
        </template>

        <template v-else-if="isLinkedMember">
            <div
                v-if="isBar"
                class="h-4 w-px bg-white/20"
                aria-hidden="true"
            />
            <Link
                :href="route('account.lapak.index')"
                :class="actionClass"
                @click="onNavigate"
            >
                {{ $t("Lapak Saya") }}
            </Link>
        </template>

        <template v-else-if="isGuest">
            <div
                v-if="isBar"
                class="h-4 w-px bg-white/20"
                aria-hidden="true"
            />
            <Link
                :href="route('login')"
                :class="actionClass"
                @click="onNavigate"
            >
                {{ $t("Log in") }}
            </Link>
        </template>
    </div>
</template>
