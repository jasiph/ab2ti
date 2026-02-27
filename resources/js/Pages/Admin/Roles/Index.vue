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
import RoleForm from "./Form.vue"; // <--- Import Form Role

const props = defineProps({
    roles: Object,
    filters: Object,
    permissionsStructure: Object, // Struktur permission untuk dikirim ke form
});

const search = ref(props.filters.search || "");

// Search Logic
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route("admin.roles.index"),
            { search: value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

// Modal State
const isModalOpen = ref(false);
const editingRole = ref(null);

const openCreateModal = () => {
    editingRole.value = null;
    isModalOpen.value = true;
};

const openEditModal = (role) => {
    editingRole.value = role;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingRole.value = null;
};

const { confirm } = useConfirm();

const deleteRole = async (role) => {
    const ok = await confirm({
        title: 'Delete Role',
        message: `Hapus role "${role.name}"? User dengan role ini mungkin akan kehilangan akses.`,
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.roles.destroy", role.id));
    }
};
</script>

<template>
    <AppLayout title="Role Management">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                Role Management
            </h2>
        </template>

        <div class="py-6">
            <BaseList :items="roles">
                <template #actions>
                    <div class="flex flex-col sm:flex-row gap-3 w-full">
                        <div class="w-full sm:w-64">
                            <BaseInput
                                v-model="search"
                                placeholder="Cari role..."
                                class="w-full"
                            />
                        </div>
                        <BaseButton @click="openCreateModal">
                            + Role Baru
                        </BaseButton>
                    </div>
                </template>

                <template #avatar>
                    <div
                        class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center border border-primary/20"
                    >
                        <svg
                            class="w-6 h-6 text-primary"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            ></path>
                        </svg>
                    </div>
                </template>

                <template #title="{ item }">
                    <span class="capitalize">{{ item.name }}</span>
                </template>

                <template #description="{ item }">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs bg-surface-variant px-2 py-0.5 rounded border border-border text-text-muted"
                        >
                            {{ item.permissions_count }} Permissions
                        </span>
                        <span
                            v-if="item.name === 'super-admin'"
                            class="text-xs font-bold text-amber-500"
                        >
                            (System Protected)
                        </span>
                    </div>
                </template>

                <template #right="{ item }">
                    <div
                        v-if="item.name !== 'super-admin'"
                        class="flex gap-3 mt-1"
                    >
                        <button
                            @click="openEditModal(item)"
                            class="text-xs font-medium text-text-muted hover:text-primary transition"
                        >
                            EDIT
                        </button>
                        <button
                            @click="deleteRole(item)"
                            class="text-xs font-medium text-text-muted hover:text-red-500 transition"
                        >
                            HAPUS
                        </button>
                    </div>
                    <div v-else class="text-xs text-text-muted italic mt-1">
                        Cannot be modified
                    </div>
                </template>

                <template #actions_mobile="{ item }">
                    <div v-if="item.name !== 'super-admin'" class="flex gap-4">
                        <button
                            @click="openEditModal(item)"
                            class="text-sm font-medium text-text-main hover:text-primary transition"
                        >
                            Edit
                        </button>
                        <button
                            @click="deleteRole(item)"
                            class="text-sm font-medium text-red-500 hover:text-red-600 transition"
                        >
                            Hapus
                        </button>
                    </div>
                </template>

                <template #pagination>
                    <BasePagination :links="roles.links" />
                </template>
            </BaseList>
        </div>

        <BaseModal :show="isModalOpen" @close="closeModal" fullscreen>
            <div class="px-6 py-4 border-b border-border">
                <h3 class="text-lg font-medium text-text-main">
                    {{
                        editingRole
                            ? "Edit Role & Permissions"
                            : "Buat Role Baru"
                    }}
                </h3>
            </div>

            <RoleForm
                :role="editingRole"
                :permissions-structure="permissionsStructure"
                @success="closeModal"
                @cancel="closeModal"
            />
        </BaseModal>
    </AppLayout>
</template>
