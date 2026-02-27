<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { useForm, Link } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const props = defineProps({
    templates: Array,
});

const selectedTemplateId = ref("");
const selectedTemplate = ref(null);
const form = useForm({
    letter_template_id: "",
    content_data: {},
});

watch(selectedTemplateId, (newId) => {
    selectedTemplate.value = props.templates.find(t => t.id === newId);
    form.letter_template_id = newId;
    form.content_data = {}; // Reset data
    
    // Initialize fields based on schema
    if (selectedTemplate.value && selectedTemplate.value.data_schema) {
        Object.keys(selectedTemplate.value.data_schema).forEach(key => {
            form.content_data[key] = "";
        });
    }
});

const submit = () => {
    form.post(route("admin.letters.store"));
};
</script>

<template>
    <AppLayout title="Create Letter">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Draft New Letter
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    
                    <!-- Step 1: Select Template -->
                    <div class="mb-6">
                        <InputLabel for="template" value="Select Document Template" />
                        <select
                            id="template"
                            v-model="selectedTemplateId"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        >
                            <option value="" disabled>-- Choose Template --</option>
                            <option v-for="template in templates" :key="template.id" :value="template.id">
                                {{ template.name }} ({{ template.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Step 2: Fill Data -->
                    <form v-if="selectedTemplate" @submit.prevent="submit">
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Fill Document Details</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="(type, key) in selectedTemplate.data_schema" :key="key">
                                    <InputLabel :for="key" :value="key.replace(/_/g, ' ').toUpperCase()" />
                                    <!-- Simple Type Handling -->
                                    <TextInput
                                        :id="key"
                                        v-model="form.content_data[key]"
                                        :type="type === 'date' ? 'date' : 'text'"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Field: {{ key }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-8 gap-4">
                            <Link :href="route('admin.letters.index')">
                                <SecondaryButton>Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Create Draft
                            </PrimaryButton>
                        </div>
                    </form>

                    <div v-else-if="!selectedTemplateId" class="text-center py-10 text-gray-500 dark:text-gray-400">
                        Please select a template to proceed.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
