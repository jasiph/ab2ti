<script setup>
import { useUndoToast } from "@/Composables/useUndoToast";

const { show, message, duration, startedAt, undo, dismiss } = useUndoToast();
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-full opacity-0 sm:translate-y-10 sm:opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="translate-y-full opacity-0 sm:translate-y-10 sm:opacity-0"
        >
            <div
                v-if="show"
                class="fixed z-[9999] flex justify-center pointer-events-none bottom-0 left-0 right-0 w-full sm:bottom-6 sm:left-1/2 sm:-translate-x-1/2 sm:w-auto sm:max-w-md"
            >
                <div
                    class="pointer-events-auto bg-surface border-t sm:border border-border shadow-2xl overflow-hidden flex flex-col w-full rounded-none sm:w-auto sm:min-w-[350px] sm:rounded-xl"
                >
                    <div class="p-4 flex items-center bg-surface">
                        <!-- Trash icon -->
                        <div class="flex-shrink-0">
                            <svg
                                class="h-6 w-6 text-red-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </div>

                        <!-- Message -->
                        <div class="ml-3 flex-1 text-left">
                            <p class="text-sm font-medium text-text-main">
                                {{ message }}
                            </p>
                        </div>

                        <!-- Undo button -->
                        <button
                            @click="undo"
                            class="ml-4 flex-shrink-0 text-sm font-bold text-primary hover:text-primary/80 uppercase tracking-wide transition"
                        >
                            {{ $t("Undo") }}
                        </button>

                        <!-- Close button -->
                        <div class="ml-3 flex-shrink-0">
                            <button
                                @click="dismiss"
                                class="inline-flex text-text-muted hover:text-text-main focus:outline-none transition"
                            >
                                <span class="sr-only">Close</span>
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="h-1 bg-surface-variant w-full">
                        <div
                            :key="startedAt"
                            class="h-1 bg-red-500 animate-shrink w-full"
                            :style="`animation-duration: ${duration}ms`"
                        ></div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
@keyframes shrink {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}
.animate-shrink {
    animation-name: shrink;
    animation-timing-function: linear;
}
</style>
