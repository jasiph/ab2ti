import { ref, reactive } from 'vue';

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'danger', // 'danger' | 'warning' | 'info'
    onConfirm: null,
    onCancel: null,
    processing: false,
});

export function useConfirm() {
    const confirm = ({
        title = 'Are you sure?',
        message = '',
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        variant = 'danger',
    } = {}) => {
        return new Promise((resolve) => {
            state.title = title;
            state.message = message;
            state.confirmText = confirmText;
            state.cancelText = cancelText;
            state.variant = variant;
            state.processing = false;
            state.onConfirm = () => {
                state.show = false;
                resolve(true);
            };
            state.onCancel = () => {
                state.show = false;
                resolve(false);
            };
            state.show = true;
        });
    };

    // Shorthand for alert-style (info, no cancel)
    const alertDialog = ({
        title = 'Notice',
        message = '',
        confirmText = 'OK',
        variant = 'info',
    } = {}) => {
        return new Promise((resolve) => {
            state.title = title;
            state.message = message;
            state.confirmText = confirmText;
            state.cancelText = '';
            state.variant = variant;
            state.processing = false;
            state.onConfirm = () => {
                state.show = false;
                resolve(true);
            };
            state.onCancel = () => {
                state.show = false;
                resolve(true);
            };
            state.show = true;
        });
    };

    return { confirm, alertDialog, state };
}
