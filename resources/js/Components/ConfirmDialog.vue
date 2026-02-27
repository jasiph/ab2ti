<script setup>
import { useConfirm } from '@/Composables/useConfirm';
import BaseModal from '@/Components/Base/BaseModal.vue';

const { state } = useConfirm();

const variantIcon = {
    danger: {
        bg: 'bg-red-100 dark:bg-red-900/30',
        text: 'text-red-600 dark:text-red-400',
        path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    },
    warning: {
        bg: 'bg-yellow-100 dark:bg-yellow-900/30',
        text: 'text-yellow-600 dark:text-yellow-400',
        path: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
    },
    info: {
        bg: 'bg-blue-100 dark:bg-blue-900/30',
        text: 'text-blue-600 dark:text-blue-400',
        path: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    },
};

const variantButton = {
    danger: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
    warning: 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500',
    info: 'bg-primary hover:bg-primary-dark focus:ring-primary',
};
</script>

<template>
    <BaseModal :show="state.show" @close="state.onCancel" maxWidth="md">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <!-- Icon -->
                <div :class="[variantIcon[state.variant]?.bg, 'shrink-0 flex items-center justify-center w-10 h-10 rounded-full']">
                    <svg :class="[variantIcon[state.variant]?.text, 'w-5 h-5']" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="variantIcon[state.variant]?.path" />
                    </svg>
                </div>

                <!-- Text -->
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-text-main">
                        {{ $t(state.title) }}
                    </h3>
                    <p v-if="state.message" class="mt-2 text-sm text-text-muted">
                        {{ $t(state.message) }}
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    v-if="state.cancelText"
                    type="button"
                    @click="state.onCancel"
                    class="px-4 py-2 text-sm font-medium text-text-muted bg-surface-variant hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition"
                >
                    {{ $t(state.cancelText) }}
                </button>
                <button
                    type="button"
                    @click="state.onConfirm"
                    :class="[variantButton[state.variant], 'px-4 py-2 text-sm font-medium text-white rounded-lg transition focus:outline-none focus:ring-2 focus:ring-offset-2']"
                >
                    {{ $t(state.confirmText) }}
                </button>
            </div>
        </div>
    </BaseModal>
</template>
