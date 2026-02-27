<script setup>
import { computed, onMounted, onUnmounted, watch } from "vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: "2xl" },
    closeable: { type: Boolean, default: true },
    fullscreen: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

// Scroll Lock Body
watch(
    () => props.show,
    (show) => {
        document.body.style.overflow = show ? "hidden" : null;
    },
);

const close = () => {
    if (props.closeable) emit("close");
};
const closeOnEscape = (e) => {
    if (e.key === "Escape" && props.show) close();
};

onMounted(() => document.addEventListener("keydown", closeOnEscape));
onUnmounted(() => {
    document.removeEventListener("keydown", closeOnEscape);
    document.body.style.overflow = null;
});

// Logic Lebar
const maxWidthClass = computed(() => {
    // Jika Fullscreen: Paksa lebar penuh
    if (props.fullscreen)
        return "sm:w-full sm:max-w-none sm:h-full sm:rounded-none sm:m-0";

    // Jika Normal: Ikuti maxWidth
    return {
        sm: "sm:max-w-sm",
        md: "sm:max-w-md",
        lg: "sm:max-w-lg",
        xl: "sm:max-w-xl",
        "2xl": "sm:max-w-2xl",
        "3xl": "sm:max-w-3xl",
        "4xl": "sm:max-w-4xl",
        "5xl": "sm:max-w-5xl",
        "6xl": "sm:max-w-6xl",
        "7xl": "sm:max-w-7xl",
    }[props.maxWidth];
});
</script>

<template>
    <Teleport to="body">
        <transition leave-active-class="duration-200">
            <div
                v-show="show"
                class="fixed inset-0 z-50 flex items-center justify-center"
                :class="fullscreen ? 'p-0' : 'px-0 py-0 sm:px-4 sm:py-6'"
            >
                <transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-show="show"
                        class="fixed inset-0 transform transition-all"
                        @click="close"
                    >
                        <div
                            class="absolute inset-0 bg-gray-900/75 dark:bg-gray-900/90 backdrop-blur-sm"
                        />
                    </div>
                </transition>

                <transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <div
                        v-show="show"
                        class="bg-surface text-text-main shadow-xl transform transition-all w-full flex flex-col overflow-hidden"
                        :class="[
                            maxWidthClass,
                            // MOBILE & FULLSCREEN DESKTOP: Tinggi penuh, sudut kotak
                            fullscreen
                                ? 'h-full rounded-none'
                                : 'h-full rounded-none sm:h-auto sm:max-h-[90vh] sm:rounded-xl',
                        ]"
                    >
                        <div class="absolute top-4 right-4 z-10">
                            <button
                                @click="close"
                                class="p-2 rounded-full bg-surface/80 hover:bg-surface-variant text-text-muted hover:text-text-main transition backdrop-blur-md border border-border/50 shadow-sm"
                            >
                                <svg
                                    class="w-6 h-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>

                        <div
                            class="flex-1 overflow-y-auto overscroll-contain p-0"
                        >
                            <slot v-if="show" />
                        </div>
                    </div>
                </transition>
            </div>
        </transition>
    </Teleport>
</template>
