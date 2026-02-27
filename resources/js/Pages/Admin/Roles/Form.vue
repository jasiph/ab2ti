<script setup>
import { watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    role: {
        type: Object,
        default: null,
    },
    // Struktur dari config('acl.permissions')
    permissionsStructure: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["success", "cancel"]);

const form = useForm({
    name: "",
    permissions: [], // Array string ['users.view', 'users.create']
});

// Helper: Check All dalam satu grup
const toggleGroup = (groupPermissions, event) => {
    const isChecked = event.target.checked;
    if (isChecked) {
        // Tambahkan semua permission grup ini ke form (hindari duplikat)
        groupPermissions.forEach((p) => {
            if (!form.permissions.includes(p)) form.permissions.push(p);
        });
    } else {
        // Hapus semua permission grup ini dari form
        form.permissions = form.permissions.filter(
            (p) => !groupPermissions.includes(p),
        );
    }
};

// Helper: Cek apakah semua item di grup terpilih (untuk status checkbox parent)
const isGroupChecked = (groupPermissions) => {
    return groupPermissions.every((p) => form.permissions.includes(p));
};

watch(
    () => props.role,
    (newRole) => {
        if (newRole) {
            // Mode EDIT
            form.name = newRole.name;
            // Kita ambil nama permission dari object relation yg dikirim backend
            form.permissions = newRole.permissions
                ? newRole.permissions.map((p) => p.name)
                : [];
            form.clearErrors();
        } else {
            // Mode CREATE
            form.reset();
            form.clearErrors();
        }
    },
    { immediate: true },
);

const submit = () => {
    if (props.role) {
        form.put(route("admin.roles.update", props.role.id), {
            preserveScroll: true,
            onSuccess: () => emit("success"),
        });
    } else {
        form.post(route("admin.roles.store"), {
            preserveScroll: true,
            onSuccess: () => emit("success"),
        });
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="p-6 h-full flex flex-col">
        <div class="flex-1 overflow-y-auto pr-2">
            <div class="mb-6">
                <InputLabel
                    for="name"
                    value="Nama Role"
                    class="text-text-main"
                />
                <BaseInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Contoh: staff-gudang"
                    :disabled="role?.name === 'super-admin'"
                    required
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    value="Hak Akses (Permissions)"
                    class="text-text-main mb-3"
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(
                            groupPermissions, groupName
                        ) in permissionsStructure"
                        :key="groupName"
                        class="border border-border rounded-lg p-4 bg-background"
                    >
                        <div
                            class="flex items-center justify-between mb-3 border-b border-border pb-2"
                        >
                            <h4
                                class="font-bold text-sm text-primary capitalize"
                            >
                                {{ groupName }}
                            </h4>
                            <label
                                class="flex items-center space-x-2 cursor-pointer"
                            >
                                <span class="text-xs text-text-muted"
                                    >Pilih Semua</span
                                >
                                <input
                                    type="checkbox"
                                    class="rounded border-border text-primary focus:ring-primary bg-surface"
                                    :checked="isGroupChecked(groupPermissions)"
                                    @change="
                                        toggleGroup(groupPermissions, $event)
                                    "
                                />
                            </label>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="permission in groupPermissions"
                                :key="permission"
                                class="flex items-start"
                            >
                                <label
                                    class="flex items-center space-x-3 cursor-pointer w-full"
                                >
                                    <input
                                        type="checkbox"
                                        :value="permission"
                                        v-model="form.permissions"
                                        class="rounded border-border text-primary focus:ring-primary bg-surface"
                                    />
                                    <span class="text-sm text-text-main">{{
                                        permission
                                    }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-border flex justify-end gap-3">
            <button
                type="button"
                @click="$emit('cancel')"
                class="px-4 py-2 text-sm font-medium text-text-muted bg-surface border border-border rounded-md hover:bg-surface-variant transition"
            >
                Batal
            </button>

            <BaseButton
                type="submit"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing || role?.name === 'super-admin'"
            >
                {{ role ? "Simpan Perubahan" : "Buat Role" }}
            </BaseButton>
        </div>
    </form>
</template>
