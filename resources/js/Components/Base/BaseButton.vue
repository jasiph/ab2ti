<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    type: { type: String, default: "button" },
    href: { type: String, default: null },
    variant: { type: String, default: "primary" }, // primary, secondary, danger, outline, ghost
    size: { type: String, default: "md" }, // sm, md, lg
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

// Mapping Style berdasarkan Variant
const variantClasses = computed(() => {
    const variants = {
        primary:
            "bg-primary hover:bg-primary-dark text-white shadow-sm focus:ring-primary/50",
        secondary:
            "bg-surface border border-border text-text-main hover:bg-surface-variant focus:ring-gray-200 dark:focus:ring-gray-700",
        danger: "bg-danger hover:brightness-90 text-white focus:ring-danger/50",
        outline:
            "bg-transparent border border-primary text-primary hover:bg-primary/10",
        ghost: "bg-transparent text-text-muted hover:text-primary hover:bg-surface-variant shadow-none",
    };
    return variants[props.variant] || variants.primary;
});

// Mapping Size
const sizeClasses = computed(() => {
    const sizes = {
        sm: "px-3 py-2 text-sm",
        md: "px-4 py-2.5 text-sm",
        lg: "px-6 py-3 text-base min-h-12",
    };
    return sizes[props.size] || sizes.md;
});
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="[
            'inline-flex items-center justify-center font-medium rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed min-h-control',
            variantClasses,
            sizeClasses,
        ]"
    >
        <slot />
    </Link>

    <button
        v-else
        :type="type"
        :disabled="disabled || loading"
        :aria-busy="loading ? 'true' : undefined"
        :class="[
            'inline-flex items-center justify-center font-medium rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed min-h-control',
            variantClasses,
            sizeClasses,
        ]"
    >
        <svg
            v-if="loading"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
        </svg>
        <slot />
    </button>
</template>
