<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { useForm, Link } from "@inertiajs/vue3";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const form = useForm({
    sender: "",
    reference_number: "",
    received_at: new Date().toISOString().split('T')[0],
    subject: "",
    disposition: "",
    file: null,
});

const submit = () => {
    form.post(route("admin.incoming-letters.store"), {
        forceFormData: true,
    });
};

const handleFileChange = (e) => {
    form.file = e.target.files[0];
};
</script>

<template>
    <AppLayout title="Archive Incoming Letter">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Archive Incoming Letter
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="sender" value="Sender" />
                                <TextInput
                                    id="sender"
                                    v-model="form.sender"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    placeholder="e.g. Sinode Wilayah"
                                />
                                <InputError :message="form.errors.sender" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="received_at" value="Received Date" />
                                <TextInput
                                    id="received_at"
                                    v-model="form.received_at"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.received_at" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="reference_number" value="Reference Number" />
                                <TextInput
                                    id="reference_number"
                                    v-model="form.reference_number"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Nomor surat dari pengirim"
                                />
                                <InputError :message="form.errors.reference_number" class="mt-2" />
                            </div>

                             <div>
                                <InputLabel for="subject" value="Subject" />
                                <TextInput
                                    id="subject"
                                    v-model="form.subject"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    placeholder="Perihal surat"
                                />
                                <InputError :message="form.errors.subject" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6">
                            <InputLabel for="file" value="Scan / File (PDF/Image)" />
                            <input
                                id="file"
                                type="file"
                                @change="handleFileChange"
                                class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-md file:border-0
                                file:text-sm file:font-semibold
                                file:bg-indigo-50 file:text-indigo-700
                                dark:file:bg-indigo-900 dark:file:text-indigo-300
                                hover:file:bg-indigo-100 dark:hover:file:bg-indigo-800"
                            />
                             <InputError :message="form.errors.file" class="mt-2" />
                        </div>

                        <div class="mt-6">
                            <InputLabel for="disposition" value="Disposition / Notes" />
                            <textarea
                                id="disposition"
                                v-model="form.disposition"
                                rows="3"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full font-sans text-sm"
                                placeholder="Instructions or notes..."
                            ></textarea>
                            <InputError :message="form.errors.disposition" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6 gap-4">
                            <Link :href="route('admin.incoming-letters.index')">
                                <SecondaryButton>Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Archive Letter
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
