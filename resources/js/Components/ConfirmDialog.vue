<script setup>
import { useConfirm } from '@/Composables/useConfirm';
import BaseModal from '@/Components/Base/BaseModal.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

const { state } = useConfirm();

const variantIcon = {
    danger: {
        bg: 'bg-danger/10',
        text: 'text-danger',
        path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    },
    warning: {
        bg: 'bg-warning/10',
        text: 'text-warning',
        path: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
    },
    info: {
        bg: 'bg-info/10',
        text: 'text-info',
        path: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    },
};

const variantButton = {
    danger: 'danger',
    warning: 'primary',
    info: 'primary',
};
</script>

<template>
    <BaseModal
        :show="state.show"
        labelled-by="confirm-dialog-title"
        maxWidth="md"
        @close="state.onCancel"
    >
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div :class="[variantIcon[state.variant]?.bg, 'shrink-0 flex items-center justify-center w-10 h-10 rounded-full']">
                    <svg :class="[variantIcon[state.variant]?.text, 'w-5 h-5']" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="variantIcon[state.variant]?.path" />
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <h3 id="confirm-dialog-title" class="text-base font-semibold text-text-main">
                        {{ $t(state.title) }}
                    </h3>
                    <p v-if="state.message" class="mt-2 text-sm text-text-muted">
                        {{ $t(state.message) }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <BaseButton
                    v-if="state.cancelText"
                    type="button"
                    variant="secondary"
                    data-modal-initial-focus
                    @click="state.onCancel"
                >
                    {{ $t(state.cancelText) }}
                </BaseButton>
                <BaseButton
                    type="button"
                    :variant="variantButton[state.variant] || 'primary'"
                    :data-modal-initial-focus="state.cancelText ? undefined : true"
                    @click="state.onConfirm"
                >
                    {{ $t(state.confirmText) }}
                </BaseButton>
            </div>
        </div>
    </BaseModal>
</template>
