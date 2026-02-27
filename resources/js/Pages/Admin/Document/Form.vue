<script setup>
import { ref, computed } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import ActionMessage from "@/Components/ActionMessage.vue";
import FormSection from "@/Components/FormSection.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseSearchSelect from "@/Components/Base/BaseSearchSelect.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { trans } from "laravel-vue-i18n";

const props = defineProps({
    document: Object,
});

const isEditing = computed(() => !!props.document);

const form = useForm({
    type: props.document?.type || "incoming_letter",
    reference_number: props.document?.reference_number || "",
    source: props.document?.source || "",
    title: props.document?.title || "",
    description: props.document?.description || "",
    document_date: props.document?.document_date || "",
    received_at: props.document?.received_at || new Date().toISOString().split('T')[0],
    congregation_id: props.document?.congregation_id || null,
    file: null,
});

const submit = () => {
    if (isEditing.value) {
        form.post(route("admin.documents.update", props.document.id), {
            _method: "put",
            preserveScroll: true,
        });
    } else {
        form.post(route("admin.documents.store"), {
            preserveScroll: true,
        });
    }
};

const typeOptions = [
    { id: 'incoming_letter', name: trans('Incoming Letter') },
    { id: 'legal', name: trans('Legal Document') },
    { id: 'certificate', name: trans('Certificate') },
    { id: 'report', name: trans('Report') },
    { id: 'other', name: trans('Other') },
];
</script>

<template>
    <AppLayout :title="isEditing ? trans('Edit Document') : trans('Archive New Document')">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ isEditing ? trans('Edit Document') : trans('Archive New Document') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FormSection @submitted="submit">
                    <template #title>
                        {{ trans('Document Details') }}
                    </template>
                    <template #description>
                        {{ trans('Manage archived document information.') }}
                    </template>

                    <template #form>
                         <!-- Type -->
                         <div class="col-span-6 sm:col-span-3">
                            <div class="w-full">
                                <label class="block font-medium text-sm text-text-main mb-1">{{ trans('Document Type') }}</label>
                                <BaseSelect
                                    v-model="form.type"
                                    :options="typeOptions"
                                    :placeholder="trans('Select Type')"
                                />
                                <p v-if="form.errors.type" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.type }}</p>
                            </div>
                        </div>

                        <!-- Source/Sender -->
                        <div class="col-span-6 sm:col-span-3">
                            <BaseInput
                                id="source"
                                v-model="form.source"
                                :label="trans('Source / Sender')"
                                :placeholder="trans('e.g. Government, Synodal Board, etc.')"
                                :error="form.errors.source"
                                required
                            />
                        </div>

                         <!-- Title -->
                        <div class="col-span-6">
                            <BaseInput
                                id="title"
                                v-model="form.title"
                                :label="trans('Title / Subject')"
                                :placeholder="trans('Document Title')"
                                :error="form.errors.title"
                                required
                            />
                        </div>

                         <!-- Reference Number -->
                        <div class="col-span-6 sm:col-span-3">
                            <BaseInput
                                id="reference_number"
                                v-model="form.reference_number"
                                :label="trans('Reference Number')"
                                :placeholder="trans('Number on the document')"
                                :error="form.errors.reference_number"
                            />
                        </div>

                        <!-- Congregation Link -->
                        <div class="col-span-6 sm:col-span-3">
                             <BaseSearchSelect
                                v-model="form.congregation_id"
                                :endpoint="route('admin.congregations.api.search')"
                                :label="trans('Related Member (Optional)')"
                                :placeholder="trans('Search Member...')"
                                :initial-label="document?.congregation?.name"
                                :error="form.errors.congregation_id"
                            />
                        </div>

                        <!-- Dates -->
                        <div class="col-span-6 sm:col-span-3">
                            <BaseInput
                                id="document_date"
                                v-model="form.document_date"
                                type="date"
                                :label="trans('Document Date')"
                                :error="form.errors.document_date"
                            />
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <BaseInput
                                id="received_at"
                                v-model="form.received_at"
                                type="date"
                                :label="trans('Received / Archived Date')"
                                :error="form.errors.received_at"
                                required
                            />
                        </div>

                        <!-- Description -->
                        <div class="col-span-6">
                            <label class="block font-medium text-sm text-text-main mb-1">{{ trans('Description') }}</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm block w-full transition sm:text-sm placeholder-text-muted/50"
                                rows="3"
                            ></textarea>
                            <p v-if="form.errors.description" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.description }}</p>
                        </div>

                        <!-- File Upload -->
                        <div class="col-span-6">
                            <label class="block font-medium text-sm text-text-main mb-1">{{ trans('File (PDF/Image)') }}</label>
                            
                            <div v-if="document?.file_path" class="mb-2 text-sm text-gray-500">
                                {{ trans('Current file:') }} <a :href="document.file_path" target="_blank" class="text-indigo-600 underline">{{ trans('View') }}</a>
                            </div>

                            <input
                                type="file"
                                @input="form.file = $event.target.files[0]"
                                class="block w-full text-sm text-slate-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-full file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-violet-50 file:text-violet-700
                                  hover:file:bg-violet-100"
                            />
                            <p v-if="form.errors.file" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.file }}</p>
                        </div>

                    </template>

                    <template #actions>
                        <ActionMessage :on="form.recentlySuccessful" class="me-3">
                            {{ trans('Saved.') }}
                        </ActionMessage>

                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ trans('Save') }}
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
