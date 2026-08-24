<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: "2xl" },
    closeable: { type: Boolean, default: true },
    fullscreen: { type: Boolean, default: false },
    labelledBy: { type: String, default: "" },
    ariaLabel: { type: String, default: "Dialog" },
});

const emit = defineEmits(["close"]);

const panel = ref(null);
const previouslyFocused = ref(null);

const FOCUSABLE =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

const visibleFocusable = () => {
    if (!panel.value) {
        return [];
    }

    return [...panel.value.querySelectorAll(FOCUSABLE)].filter((element) => {
        const style = window.getComputedStyle(element);

        return (
            style.display !== "none" &&
            style.visibility !== "hidden" &&
            element.getClientRects().length > 0
        );
    });
};

const focusInitial = async () => {
    await nextTick();

    const preferred = panel.value?.querySelector("[data-modal-initial-focus]");
    if (preferred instanceof HTMLElement) {
        preferred.focus();
        return;
    }

    const focusable = visibleFocusable();
    if (focusable[0] instanceof HTMLElement) {
        focusable[0].focus();
        return;
    }

    panel.value?.focus();
};

watch(
    () => props.show,
    async (show) => {
        document.body.style.overflow = show ? "hidden" : null;

        if (show) {
            previouslyFocused.value =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
            await focusInitial();
            return;
        }

        const opener = previouslyFocused.value;
        previouslyFocused.value = null;
        await nextTick();
        opener?.focus?.();
    },
);

const close = () => {
    if (props.closeable) {
        emit("close");
    }
};

const onKeydown = (event) => {
    if (!props.show) {
        return;
    }

    if (event.key === "Escape") {
        if (props.closeable) {
            event.preventDefault();
            close();
        }
        return;
    }

    if (event.key !== "Tab") {
        return;
    }

    const focusable = visibleFocusable();
    if (focusable.length === 0) {
        event.preventDefault();
        panel.value?.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
        return;
    }

    if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
};

onMounted(() => document.addEventListener("keydown", onKeydown));
onUnmounted(() => {
    document.removeEventListener("keydown", onKeydown);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    if (props.fullscreen) {
        return "sm:w-full sm:max-w-none sm:h-full sm:rounded-none sm:m-0";
    }

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
                role="dialog"
                aria-modal="true"
                :aria-labelledby="labelledBy || undefined"
                :aria-label="labelledBy ? undefined : ariaLabel"
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
                        ref="panel"
                        tabindex="-1"
                        class="bg-surface text-text-main shadow-xl transform transition-all w-full flex flex-col overflow-hidden outline-none"
                        :class="[
                            maxWidthClass,
                            fullscreen
                                ? 'h-full rounded-none'
                                : 'h-full rounded-none sm:h-auto sm:max-h-[90vh] sm:rounded-xl',
                        ]"
                    >
                        <div v-if="closeable" class="absolute top-4 right-4 z-10">
                            <button
                                type="button"
                                @click="close"
                                class="p-2 rounded-full bg-surface/80 hover:bg-surface-variant text-text-muted hover:text-text-main transition backdrop-blur-md border border-border/50 shadow-sm min-h-10 min-w-10 inline-flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                aria-label="Tutup"
                            >
                                <svg
                                    class="w-6 h-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
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
