<script setup>
import { ref, watch } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import BaseModal from "@/Components/Base/BaseModal.vue";
import { trans } from "laravel-vue-i18n";
import { debounce } from "@/Utils/debounce";
import axios from "axios";

const props = defineProps({
    congregations: Object,
    filters: Object,
});

const search = ref(props.filters.search || "");
const statusFilter = ref(props.filters.status || "all");
const genderFilter = ref(props.filters.gender || "all");

// Birthday Feature
const showBirthdayModal = ref(false);
const birthdayList = ref([]);
const isLoadingBirthdays = ref(false);

const openBirthdayModal = async () => {
    showBirthdayModal.value = true;
    if (birthdayList.value.length === 0) {
        isLoadingBirthdays.value = true;
        try {
            const response = await axios.get(route('admin.congregations.birthdays'));
            birthdayList.value = response.data;
        } catch (error) {
            console.error("Failed to fetch birthdays", error);
        } finally {
            isLoadingBirthdays.value = false;
        }
    }
};

const debouncedSearch = debounce((value) => {
    router.get(
        route("admin.congregations.index"),
        { search: value, status: statusFilter.value, gender: genderFilter.value },
        { preserveState: true, replace: true }
    );
}, 300);

watch(search, (value) => {
    debouncedSearch(value);
});

watch([statusFilter, genderFilter], () => {
    router.get(
        route("admin.congregations.index"),
        { search: search.value, status: statusFilter.value, gender: genderFilter.value },
        { preserveState: true, replace: true }
    );
});

const statusColors = {
    active: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300",
    inactive: "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300",
    moved: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300",
    deceased: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300",
};
</script>

<template>
    <AppLayout :title="trans('Congregation Management')">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ trans("Congregation Management") }}
                </h2>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button
                        @click="openBirthdayModal"
                        class="px-4 py-2 bg-pink-600 text-white rounded-md hover:bg-pink-700 transition w-full sm:w-auto text-center flex items-center justify-center gap-2"
                    >
                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0L6 18c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927.184"/>
                        </svg>
                        {{ trans("Birthdays") }}
                    </button>
                    <Link
                        :href="route('admin.congregations.create')"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition w-full sm:w-auto text-center"
                    >
                        {{ trans("Add New Member") }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters & Search -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4">
                    <TextInput
                        v-model="search"
                        type="text"
                        :placeholder="trans('Search by Name, NIJ, or NIK...')"
                        class="w-full sm:w-1/3"
                    />
                    <select
                        v-model="statusFilter"
                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full sm:w-auto"
                    >
                        <option value="all">{{ trans("All Status") }}</option>
                        <option value="active">{{ trans("Active") }}</option>
                        <option value="inactive">{{ trans("Inactive") }}</option>
                        <option value="moved">{{ trans("Moved") }}</option>
                        <option value="deceased">{{ trans("Deceased") }}</option>
                    </select>
                    <select
                        v-model="genderFilter"
                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full sm:w-auto"
                    >
                        <option value="all">{{ trans("All Gender") }}</option>
                        <option value="male">{{ trans("Male") }}</option>
                        <option value="female">{{ trans("Female") }}</option>
                    </select>
                </div>

                <!-- Mobile-First List View -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="member in congregations.data"
                        :key="member.id"
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 flex flex-col justify-between hover:shadow-2xl transition"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-4">
                                <!-- Avatar Placeholder or Image -->
                                <div v-if="member.photo_path" class="h-12 w-12 rounded-full overflow-hidden">
                                     <img :src="member.photo_path" alt="Photo" class="h-full w-full object-cover" />
                                </div>
                                <div v-else class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 font-bold text-lg">
                                    {{ member.name.charAt(0) }}
                                </div>
                                
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        {{ member.name }}
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ member.unit_number }} 
                                        <span v-if="member.nik">| {{ member.nik }}</span>
                                    </p>
                                </div>
                            </div>
                            
                            <span
                                :class="[
                                    'px-2 py-1 text-xs font-semibold rounded-full',
                                    statusColors[member.status]
                                ]"
                            >
                                {{ trans(member.status.charAt(0).toUpperCase() + member.status.slice(1)) }}
                            </span>
                        </div>

                        <div class="mt-4 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                            <div class="flex items-center">
                                <span class="w-20 font-semibold">{{ trans("Gender") }}:</span>
                                <span>{{ trans(member.gender === 'male' ? 'Male' : 'Female') }}</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-20 font-semibold">{{ trans("Age") }}:</span>
                                <span>
                                    {{ member.date_of_birth ? new Date().getFullYear() - new Date(member.date_of_birth).getFullYear() : '-' }} {{ trans('Years') }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3 border-t border-gray-100 dark:border-gray-700 pt-4">
                           <Link
                                :href="route('admin.congregations.edit', member.id)"
                                class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150"
                            >
                                {{ trans("Edit") }}
                            </Link>

                            <Link
                                :href="route('admin.congregations.show', member.id)"
                                class="inline-flex items-center px-3 py-1 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                            >
                                {{ trans("View") }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="congregations.data.length === 0" class="text-center py-12">
                     <p class="text-gray-500 dark:text-gray-400">{{ trans("No congregation members found.") }}</p>
                </div>

                <!-- Pagination -->
                 <div class="mt-6">
                    <!-- Simple Pagination Implementation or use Shared Component if available -->
                    <div class="flex justify-between items-center" v-if="congregations.prev_page_url || congregations.next_page_url">
                         <Link
                            v-if="congregations.prev_page_url"
                            :href="congregations.prev_page_url"
                            class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            {{ trans("Previous") }}
                        </Link>
                        <div v-else></div> <!-- Spacer -->

                        <span class="text-sm text-gray-700 dark:text-gray-300">
                             {{ trans("Page") }} {{ congregations.current_page }} {{ trans("of") }} {{ congregations.last_page }}
                        </span>

                        <Link
                            v-if="congregations.next_page_url"
                            :href="congregations.next_page_url"
                            class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            {{ trans("Next") }}
                        </Link>
                         <div v-else></div> <!-- Spacer -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Birthday Modal -->
        <BaseModal :show="showBirthdayModal" @close="showBirthdayModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ trans('Birthdays This Month') }} ({{ new Date().toLocaleString('default', { month: 'long' }) }})
                    </h2>
                    <button @click="showBirthdayModal = false" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="isLoadingBirthdays" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                    <p class="mt-2 text-gray-500">{{ trans('Loading...') }}</p>
                </div>

                <div v-else-if="birthdayList.length === 0" class="text-center py-8 text-gray-500">
                     {{ trans('No birthdays found in this month.') }}
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto pr-2">
                    <div v-for="member in birthdayList" :key="member.id" class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                         <div v-if="member.photo_path" class="h-10 w-10 rounded-full overflow-hidden flex-shrink-0 mr-3">
                             <img :src="member.photo_path" alt="Photo" class="h-full w-full object-cover" />
                        </div>
                        <div v-else class="h-10 w-10 rounded-full bg-pink-100 flex items-center justify-center text-pink-500 font-bold text-sm flex-shrink-0 mr-3">
                            {{ member.name.charAt(0) }}
                        </div>
                        
                        <div>
                            <Link :href="route('admin.congregations.show', member.id)" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600">
                                {{ member.name }}
                            </Link>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ new Date(member.date_of_birth).getDate() }} {{ new Date().toLocaleString('default', { month: 'short' }) }} 
                                ({{ new Date().getFullYear() - new Date(member.date_of_birth).getFullYear() }} {{ trans('Years') }})
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showBirthdayModal = false">
                        {{ trans('Back') }}
                    </SecondaryButton>
                </div>
            </div>
        </BaseModal>
    </AppLayout>
</template>
