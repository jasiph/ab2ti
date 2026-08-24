<script setup>
import { router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";
import BaseButton from "@/Components/Base/BaseButton.vue";
import { ref } from "vue";

const props = defineProps({
    transitions: { type: Array, default: () => [] },
    action: { type: String, required: true },
    canModerate: { type: Boolean, default: false },
});

const note = ref("");
const { confirm, alertDialog } = useConfirm();

const needsNote = (value) => value === "rejected" || value === "suspended";

const submit = async (status) => {
    if (!props.canModerate) {
        return;
    }

    if (needsNote(status) && !note.value.trim()) {
        await alertDialog({
            title: "Alasan diperlukan",
            message: "Isi alasan penolakan atau penangguhan terlebih dahulu.",
            confirmText: "Mengerti",
            variant: "warning",
        });
        return;
    }

    if (needsNote(status)) {
        const ok = await confirm({
            title: "Ubah status publikasi",
            message: "Tindakan ini membuat konten tidak tayang ke publik. Lanjutkan?",
            confirmText: "Ya, ubah",
            variant: "danger",
        });

        if (!ok) {
            return;
        }
    }

    router.post(props.action, {
        status,
        note: note.value,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div v-if="canModerate && transitions.length" class="space-y-3">
        <label class="block text-sm font-medium text-text-main">
            Alasan (wajib jika menolak atau menangguhkan)
            <textarea
                v-model="note"
                rows="2"
                class="mt-1 block w-full rounded-md border-border bg-surface text-text-main focus:border-primary focus:ring-primary"
            />
        </label>
        <div class="flex flex-wrap gap-2">
            <BaseButton
                v-for="item in transitions"
                :key="item.value"
                type="button"
                size="sm"
                :variant="needsNote(item.value) ? 'danger' : 'secondary'"
                @click="submit(item.value)"
            >
                {{ item.label }}
            </BaseButton>
        </div>
    </div>
</template>
