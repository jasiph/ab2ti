<script setup>
import { ref, computed } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import ActionMessage from "@/Components/ActionMessage.vue";
import FormSection from "@/Components/FormSection.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseSearchSelect from "@/Components/Base/BaseSearchSelect.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { trans } from "laravel-vue-i18n";

console.log('Congregation Form Loaded');

const props = defineProps({
    congregation: Object,
});

const form = useForm({
    unit_number: props.congregation?.unit_number || "",
    name: props.congregation?.name || "",
    gender: props.congregation?.gender || "male",
    date_of_birth: props.congregation?.date_of_birth || "",
    place_of_birth: props.congregation?.place_of_birth || "",
    status: props.congregation?.status || "active",
    address: props.congregation?.address || "",
    phone: props.congregation?.phone || "",
    email: props.congregation?.email || "",
    marital_status: props.congregation?.marital_status || "single",
    baptism_date: props.congregation?.baptism_date || "",
    sidi_date: props.congregation?.sidi_date || "",
    join_date: props.congregation?.join_date || "",
    father_id: props.congregation?.father_id || null,
    mother_id: props.congregation?.mother_id || null,
    spouse_id: props.congregation?.spouse_id || null,
    head_of_family_id: props.congregation?.head_of_family_id || null,
    nik: props.congregation?.nik || "",
});

const isEditing = computed(() => !!props.congregation);

const submit = () => {
    if (isEditing.value) {
        form.put(route("admin.congregations.update", props.congregation.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route("admin.congregations.store"), {
            preserveScroll: true,
        });
    }
};

// Tabs Logic
const activeTab = ref('personal');
const tabs = [
    { id: 'personal', label: 'Personal Info' },
    { id: 'contact', label: 'Contact & Address' },
    { id: 'church', label: 'Church Data' },
    { id: 'family', label: 'Family Relations' },
];

const genderOptions = [
    { id: 'male', name: trans('Male') },
    { id: 'female', name: trans('Female') },
];

const statusOptions = [
    { id: 'active', name: trans('Active') },
    { id: 'inactive', name: trans('Inactive') },
    { id: 'moved', name: trans('Moved') },
    { id: 'deceased', name: trans('Deceased') },
];

const maritalStatusOptions = [
    { id: 'single', name: trans('Single') },
    { id: 'married', name: trans('Married') },
    { id: 'widowed', name: trans('Widowed') },
    { id: 'divorced', name: trans('Divorced') },
];

</script>

<template>
    <AppLayout :title="isEditing ? trans('Edit Member') : trans('Create Member')">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ isEditing ? trans('Edit Member') : trans('Create Member') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Tabs Navigation -->
                <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                activeTab === tab.id
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            {{ trans(tab.label) }}
                        </button>
                    </nav>
                </div>

                <FormSection @submitted="submit">
                    <template #title>
                        {{ trans('Member Information') }}
                    </template>
                    <template #description>
                        {{ trans('Manage congregation member details.') }}
                    </template>

                    <template #form>
                        <!-- Tab 1: Personal Info -->
                        <div v-show="activeTab === 'personal'" class="col-span-6 grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="unit_number"
                                    v-model="form.unit_number"
                                    :label="trans('NIJ (Nomor Induk Jemaat)')"
                                    :error="form.errors.unit_number"
                                    required
                                />
                            </div>

                             <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="nik"
                                    v-model="form.nik"
                                    :label="trans('NIK')"
                                    :error="form.errors.nik"
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="name"
                                    v-model="form.name"
                                    :label="trans('Full Name')"
                                    :error="form.errors.name"
                                    required
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <div class="w-full">
                                     <label class="block font-medium text-sm text-text-main mb-1">{{ trans('Gender') }}</label>
                                     <BaseSelect
                                        v-model="form.gender"
                                        :options="genderOptions"
                                        :placeholder="trans('Select Gender')"
                                    />
                                     <p v-if="form.errors.gender" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.gender }}</p>
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="place_of_birth"
                                    v-model="form.place_of_birth"
                                    :label="trans('Place of Birth')"
                                    :error="form.errors.place_of_birth"
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="date_of_birth"
                                    v-model="form.date_of_birth"
                                    type="date"
                                    :label="trans('Date of Birth')"
                                    :error="form.errors.date_of_birth"
                                />
                            </div>
                        </div>

                         <!-- Tab 2: Contact & Address -->
                        <div v-show="activeTab === 'contact'" class="col-span-6 grid grid-cols-6 gap-6">
                            <div class="col-span-6">
                                <label class="block font-medium text-sm text-text-main mb-1">{{ trans('Address') }}</label>
                                <textarea
                                    id="address"
                                    v-model="form.address"
                                    class="border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm block w-full transition sm:text-sm placeholder-text-muted/50"
                                    :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': form.errors.address }"
                                    rows="3"
                                ></textarea>
                                <p v-if="form.errors.address" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.address }}</p>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="phone"
                                    v-model="form.phone"
                                    :label="trans('Phone')"
                                    :error="form.errors.phone"
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :label="trans('Email')"
                                    :error="form.errors.email"
                                />
                            </div>
                        </div>

                        <!-- Tab 3: Church Data -->
                        <div v-show="activeTab === 'church'" class="col-span-6 grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <div class="w-full">
                                     <label class="block font-medium text-sm text-text-main mb-1">{{ trans('Status') }}</label>
                                     <BaseSelect
                                        v-model="form.status"
                                        :options="statusOptions"
                                        :placeholder="trans('Select Status')"
                                    />
                                    <p v-if="form.errors.status" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.status }}</p>
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                 <div class="w-full">
                                     <label class="block font-medium text-sm text-text-main mb-1">{{ trans('Marital Status') }}</label>
                                     <BaseSelect
                                        v-model="form.marital_status"
                                        :options="maritalStatusOptions"
                                        :placeholder="trans('Select Marital Status')"
                                    />
                                    <p v-if="form.errors.marital_status" class="text-sm text-red-600 mt-1 animate-pulse">{{ form.errors.marital_status }}</p>
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="baptism_date"
                                    v-model="form.baptism_date"
                                    type="date"
                                    :label="trans('Baptism Date')"
                                    :error="form.errors.baptism_date"
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="sidi_date"
                                    v-model="form.sidi_date"
                                    type="date"
                                    :label="trans('Sidi Date')"
                                    :error="form.errors.sidi_date"
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseInput
                                    id="join_date"
                                    v-model="form.join_date"
                                    type="date"
                                    :label="trans('Join Date')"
                                    :error="form.errors.join_date"
                                />
                            </div>
                        </div>

                        <!-- Tab 4: Family Relations -->
                        <div v-show="activeTab === 'family'" class="col-span-6 grid grid-cols-6 gap-6">
                            
                            <div class="col-span-6 sm:col-span-3">
                                <BaseSearchSelect
                                    v-model="form.father_id"
                                    :endpoint="route('admin.congregations.api.search')"
                                    :label="trans('Father')"
                                    :placeholder="trans('Search by Name...')"
                                    :initial-label="congregation?.father?.name"
                                    :error="form.errors.father_id"
                                />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <BaseSearchSelect
                                    v-model="form.mother_id"
                                    :endpoint="route('admin.congregations.api.search')"
                                    :label="trans('Mother')"
                                    :placeholder="trans('Search by Name...')"
                                    :initial-label="congregation?.mother?.name"
                                    :error="form.errors.mother_id"
                                />
                            </div>

                             <div class="col-span-6 sm:col-span-3">
                                <BaseSearchSelect
                                    v-model="form.spouse_id"
                                    :endpoint="route('admin.congregations.api.search')"
                                    :label="trans('Spouse')"
                                    :placeholder="trans('Search by Name...')"
                                    :initial-label="congregation?.spouse?.name"
                                    :error="form.errors.spouse_id"
                                />
                            </div>
                            
                            <div class="col-span-6 sm:col-span-3">
                                <BaseSearchSelect
                                    v-model="form.head_of_family_id"
                                    :endpoint="route('admin.congregations.api.search')"
                                    :label="trans('Head of Family')"
                                    :placeholder="trans('Search by Name...')"
                                    :initial-label="congregation?.head_of_family?.name"
                                    :error="form.errors.head_of_family_id"
                                />
                            </div>

                            <div class="col-span-6">
                                <p class="text-sm text-gray-500">
                                    {{ trans('Note: Search for existing members to link family relationships.') }}
                                </p>
                            </div>
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
