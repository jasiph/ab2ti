import { ref } from 'vue';

const show = ref(false);
const message = ref('');
const timer = ref(null);
const duration = ref(5000);
const startedAt = ref(null);

let _onUndo = null;
let _onExpire = null;

export function useUndoToast() {
    const showUndoToast = ({ message: msg, onUndo, onExpire, duration: dur = 5000 }) => {
        // Clear any existing toast
        if (timer.value) {
            clearTimeout(timer.value);
            // Fire the previous onExpire immediately if there was one
            if (_onExpire) _onExpire();
        }

        message.value = msg;
        duration.value = dur;
        startedAt.value = Date.now();
        _onUndo = onUndo || null;
        _onExpire = onExpire || null;
        show.value = true;

        timer.value = setTimeout(() => {
            show.value = false;
            if (_onExpire) _onExpire();
            _onExpire = null;
            _onUndo = null;
            timer.value = null;
        }, dur);
    };

    const undo = () => {
        if (timer.value) {
            clearTimeout(timer.value);
            timer.value = null;
        }
        show.value = false;
        if (_onUndo) _onUndo();
        _onUndo = null;
        _onExpire = null;
    };

    const dismiss = () => {
        if (timer.value) {
            clearTimeout(timer.value);
            timer.value = null;
        }
        show.value = false;
        if (_onExpire) _onExpire();
        _onExpire = null;
        _onUndo = null;
    };

    return {
        show,
        message,
        duration,
        startedAt,
        showUndoToast,
        undo,
        dismiss,
    };
}
