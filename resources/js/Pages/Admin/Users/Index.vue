<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseModal from "@/Components/Base/BaseModal.vue";
import UserForm from "./Form.vue"; // <--- Import Form yang baru kita buat

const props = defineProps({
    users: Object,
    filters: Object,
    availableRoles: Array,
});

const search = ref(props.filters.search || "");

// Search Logic
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route("admin.users.index"),
            { search: value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

// === MODAL STATE MANAGEMENT ===
const isModalOpen = ref(false);
const editingUser = ref(null); // Menyimpan object user yang sedang diedit (atau null jika create)

const openCreateModal = () => {
    editingUser.value = null; // Reset ke mode Create
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user; // Set data user untuk diedit
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
};

const { confirm } = useConfirm();

const deleteUser = async (user) => {
    const ok = await confirm({
        title: 'Delete User',
        message: `Apakah Anda yakin ingin menghapus user ${user.name}?`,
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.users.destroy", user.id));
    }
};
</script>

<template>
    <AppLayout title="User Management">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                User Management
            </h2>
        </template>

        <div class="py-6">
            <BaseList :items="users">
                <template #actions>
                    <div class="flex flex-col sm:flex-row gap-3 w-full">
                        <div class="w-full sm:w-64">
                            <BaseInput
                                v-model="search"
                                placeholder="Cari nama atau email..."
                                class="w-full"
                            />
                        </div>
                        <BaseButton @click="openCreateModal">
                            + User Baru
                        </BaseButton>
                    </div>
                </template>

                <template #avatar="{ item }">
                    <img
                        class="h-10 w-10 rounded-full object-cover border border-border"
                        :src="item.profile_photo_url"
                        :alt="item.name"
                    />
                </template>

                <template #title="{ item }">
                    {{ item.name }}
                </template>

                <template #description="{ item }">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3"
                    >
                        <span class="flex items-center">
                            {{ item.email }}
                        </span>
                        <span
                            v-if="item.roles.length"
                            class="hidden sm:inline-block text-border"
                            >|</span
                        >
                        <div class="flex gap-1">
                            <span
                                v-for="role in item.roles"
                                :key="role.id"
                                class="text-xs font-medium px-2 py-0.5 rounded bg-primary/10 text-primary border border-primary/20"
                            >
                                {{ role.name }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #right="{ item }">
                    <div class="flex flex-col items-end gap-2">
                        <div class="flex gap-3 mt-1">
                            <button
                                @click="openEditModal(item)"
                                class="text-xs font-medium text-text-muted hover:text-primary transition"
                            >
                                EDIT
                            </button>
                            <button
                                @click="deleteUser(item)"
                                class="text-xs font-medium text-text-muted hover:text-red-500 transition"
                            >
                                HAPUS
                            </button>
                        </div>
                    </div>
                </template>

                <template #actions_mobile="{ item }">
                    <button
                        @click="openEditModal(item)"
                        class="text-sm font-medium text-text-main hover:text-primary transition"
                    >
                        Edit
                    </button>
                    <button
                        @click="deleteUser(item)"
                        class="text-sm font-medium text-red-500 hover:text-red-600 transition"
                    >
                        Hapus
                    </button>
                </template>

                <template #pagination>
                    <BasePagination :links="users.links" />
                </template>
            </BaseList>
        </div>

        <BaseModal :show="isModalOpen" @close="closeModal">
            <div class="px-6 py-4 border-b border-border">
                <h3 class="text-lg font-medium text-text-main">
                    {{ editingUser ? "Edit User" : "Tambah User Baru" }}
                </h3>
            </div>

            <UserForm
                :user="editingUser"
                :roles="availableRoles"
                @success="closeModal"
                @cancel="closeModal"
            />
        </BaseModal>
    </AppLayout>
</template>
