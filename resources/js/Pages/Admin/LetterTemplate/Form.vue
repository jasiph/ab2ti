<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { useForm, Link } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const props = defineProps({
    letterTemplate: Object,
});

const form = useForm({
    name: props.letterTemplate?.name || "",
    code: props.letterTemplate?.code || "",
    content_template: props.letterTemplate?.content_template || "",
    data_schema: props.letterTemplate?.data_schema
        ? JSON.stringify(props.letterTemplate.data_schema, null, 2)
        : "{}",
});

const { alertDialog } = useConfirm();

const submit = async () => {
    try {
        const parsedSchema = JSON.parse(form.data_schema);
        form.transform((data) => ({
            ...data,
            data_schema: parsedSchema,
        }));
        
        if (props.letterTemplate) {
            form.put(route("admin.letter-templates.update", props.letterTemplate.id));
        } else {
            form.post(route("admin.letter-templates.store"));
        }
    } catch (e) {
        await alertDialog({
            title: 'Invalid JSON',
            message: 'Invalid JSON in Data Schema',
            variant: 'warning',
        });
    }
};
</script>

<template>
    <AppLayout :title="props.letterTemplate ? 'Edit Template' : 'Create Template'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ props.letterTemplate ? 'Edit Template' : 'Create Template' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="name" value="Template Name" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="code" value="Template Code (Unique)" />
                                <TextInput
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.code" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6">
                            <InputLabel for="content_template" value="HTML Content Template" />
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Use Blade-like syntax or placeholders.</p>
                            <textarea
                                id="content_template"
                                v-model="form.content_template"
                                rows="10"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full font-mono text-sm"
                            ></textarea>
                            <InputError :message="form.errors.content_template" class="mt-2" />
                        </div>

                        <div class="mt-6">
                            <InputLabel for="data_schema" value="Data Schema (JSON)" />
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Define fields required for this template (e.g. name, date).</p>
                            <textarea
                                id="data_schema"
                                v-model="form.data_schema"
                                rows="10"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full font-mono text-sm"
                            ></textarea>
                            <InputError :message="form.errors.data_schema" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6 gap-4">
                            <Link :href="route('admin.letter-templates.index')">
                                <SecondaryButton>Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Save Template
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
