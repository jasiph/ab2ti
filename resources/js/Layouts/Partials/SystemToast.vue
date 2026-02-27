<script setup>
import { ref, watch, computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const show = ref(false);
const message = ref("");
const type = ref("success"); // success, error, warning

const flash = computed(() => page.props.flash);

watch(
    flash,
    (newFlash) => {
        show.value = false;

        // Cek validitas data flash sebelum memproses
        if (!newFlash) return;

        if (newFlash.success) {
            type.value = "success";
            message.value = newFlash.success;
            show.value = true;
        } else if (newFlash.error) {
            type.value = "error";
            message.value = newFlash.error;
            show.value = true;
        } else if (newFlash.warning) {
            type.value = "warning";
            message.value = newFlash.warning;
            show.value = true;
        }

        if (show.value) {
            setTimeout(() => {
                show.value = false;
            }, 4000);
        }
    },
    { deep: true, immediate: true },
);
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
                class="fixed z-[999] flex justify-center pointer-events-none /* MOBILE: Menempel di bawah, lebar penuh */ bottom-0 left-0 right-0 w-full /* DESKTOP (>640px): Floating di tengah bawah, ada jarak */ sm:bottom-6 sm:left-1/2 sm:-translate-x-1/2 sm:w-auto sm:max-w-md"
            >
                <div
                    class="pointer-events-auto bg-surface border-t sm:border border-border shadow-2xl overflow-hidden flex flex-col /* MOBILE: Kotak tajam (rounded-none) agar menyatu dengan layar */ w-full rounded-none /* DESKTOP: Rounded cantik */ sm:w-auto sm:min-w-[350px] sm:rounded-xl"
                >
                    <div class="p-4 flex items-start bg-surface">
                        <div class="flex-shrink-0">
                            <svg
                                v-if="type === 'success'"
                                class="h-6 w-6 text-green-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <svg
                                v-if="type === 'error'"
                                class="h-6 w-6 text-red-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <svg
                                v-if="type === 'warning'"
                                class="h-6 w-6 text-yellow-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>

                        <div class="ml-3 w-0 flex-1 pt-0.5 text-left">
                            <p
                                class="text-sm font-bold capitalize"
                                :class="{
                                    'text-green-600 dark:text-green-400':
                                        type === 'success',
                                    'text-red-600 dark:text-red-400':
                                        type === 'error',
                                    'text-yellow-600 dark:text-yellow-400':
                                        type === 'warning',
                                }"
                            >
                                {{
                                    $t(
                                        type.charAt(0).toUpperCase() +
                                            type.slice(1),
                                    )
                                }}
                            </p>
                            <p
                                class="mt-1 text-sm text-text-muted leading-snug"
                            >
                                {{ message }}
                            </p>
                        </div>

                        <div class="ml-4 flex-shrink-0 flex">
                            <button
                                @click="show = false"
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

                    <div class="h-1 bg-surface-variant w-full">
                        <div
                            class="h-1 animate-shrink w-full"
                            :class="{
                                'bg-green-500': type === 'success',
                                'bg-red-500': type === 'error',
                                'bg-yellow-500': type === 'warning',
                            }"
                            style="animation-duration: 4000ms"
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
