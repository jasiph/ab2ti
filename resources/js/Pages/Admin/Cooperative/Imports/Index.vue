<script setup>
import { useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseFileInput from "@/Components/Base/BaseFileInput.vue";

const props = defineProps({
    batches: Object,
    canManage: Boolean,
    limits: Object,
});

const form = useForm({
    file: null,
});

const onFile = (file) => {
    form.file = file;
};

const submit = () => {
    form.post(route("admin.cooperative.imports.store"), { forceFormData: true });
};
</script>

<template>
    <AppLayout :title="$t('Legacy Cooperative import')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                {{ $t("Legacy Cooperative import") }}
            </h2>
        </template>

        <div class="space-y-6">
            <BaseCard v-if="canManage">
                <template #header>{{ $t("Upload legacy spreadsheet") }}</template>
                <p class="text-sm text-text-muted mb-4">{{ $t("Upload legacy spreadsheet help") }}</p>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Preview does not change Cooperative/PSN canonical data.") }}
                    CSV/XLSX · max {{ limits?.max_kilobytes ?? 10240 }} KB · max {{ limits?.max_rows ?? 5000 }} {{ $t("data rows") }}.
                </p>
                <form class="space-y-4" @submit.prevent="submit">
                    <BaseFileInput
                        id="cooperative-import-file"
                        :label="$t('Spreadsheet file')"
                        accept=".csv,.xlsx"
                        required
                        :error="form.errors.file"
                        @change="onFile"
                    />
                    <BaseButton type="submit" :disabled="form.processing || !form.file">
                        {{ $t("Upload and parse") }}
                    </BaseButton>
                </form>
            </BaseCard>

            <BaseList :items="batches">
                <template #header>
                    <p class="text-sm text-text-muted">{{ $t("Legacy import batches help") }}</p>
                </template>
                <template #title="{ item }">
                    {{ item.original_filename }}
                </template>
                <template #description="{ item }">
                    <span>{{ item.status }}</span>
                    <span v-if="item.duplicate_of_batch_id"> · {{ $t("Duplicate file warning") }}</span>
                </template>
                <template #right="{ item }">
                    <a
                        :href="route('admin.cooperative.imports.show', item.id)"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        {{ $t("Open") }}
                    </a>
                </template>
                <template #actions_mobile="{ item }">
                    <BaseButton
                        :href="route('admin.cooperative.imports.show', item.id)"
                        variant="secondary"
                        size="sm"
                    >
                        {{ $t("Open") }}
                    </BaseButton>
                </template>
                <template #pagination>
                    <BasePagination :links="batches.links" />
                </template>
            </BaseList>
        </div>
    </AppLayout>
</template>
