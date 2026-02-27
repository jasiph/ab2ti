<script setup>
defineProps({
    center: {
        type: Boolean,
        default: false,
    },
    noPadding: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="bg-background"
        :class="[
            center
                ? // UBAH DISINI:
                  // Ganti 'min-h-[80vh]' atau 'min-h-screen' menjadi 'min-h-dvh'
                  // dvh = Dynamic Viewport Height (Solusi modern mobile)
                  'min-h-dvh w-full flex items-center justify-center p-4'
                : // Mode normal
                  'block h-full',
        ]"
    >
        <div
            class="bg-surface border border-border shadow-sm rounded-lg overflow-hidden transition-colors duration-200 flex flex-col"
            :class="[
                // Tambahkan 'my-auto' untuk keamanan ekstra flexbox
                center
                    ? 'w-full max-w-md bg-white dark:bg-gray-800 relative'
                    : 'h-full bg-surface',
            ]"
        >
            <div
                v-if="$slots.header"
                class="px-6 py-4 border-b border-border bg-surface-variant/30"
            >
                <h3 class="text-lg font-medium text-text-main">
                    <slot name="header" />
                </h3>
            </div>

            <div
                class="text-text-main flex-1"
                :class="noPadding ? 'p-0' : 'p-6'"
            >
                <slot />
            </div>

            <div
                v-if="$slots.footer"
                class="px-6 py-4 bg-surface-variant/30 border-t border-border flex items-center"
            >
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
