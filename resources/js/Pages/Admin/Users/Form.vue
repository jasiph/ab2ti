<script setup>
import { watch, onMounted } from "vue";
import { useForm } from "@inertiajs/vue3";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    user: {
        type: Object,
        default: null, // Jika null, berarti mode CREATE
    },
    roles: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(["success", "cancel"]);

// Inisialisasi Form
const form = useForm({
    name: "",
    email: "",
    role: "",
    password: "",
});

// Logika Pengisian Data (Watch prop 'user')
// Kita pakai watch immediate agar saat komponen di-mount atau prop berubah, form terisi
watch(
    () => props.user,
    (newUser) => {
        if (newUser) {
            // Mode EDIT
            form.name = newUser.name;
            form.email = newUser.email;
            form.role = newUser.roles.length > 0 ? newUser.roles[0].name : "";
            form.password = ""; // Password selalu kosongkan saat edit
            form.clearErrors();
        } else {
            // Mode CREATE (Reset)
            form.reset();
            form.clearErrors();
        }
    },
    { immediate: true },
);

const submit = () => {
    if (props.user) {
        // UPDATE (PUT)
        form.put(route("admin.users.update", props.user.id), {
            preserveScroll: true,
            onSuccess: () => emit("success"),
        });
    } else {
        // STORE (POST)
        form.post(route("admin.users.store"), {
            preserveScroll: true,
            onSuccess: () => emit("success"), // Emit sukses biar parent menutup modal
        });
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="p-6">
        <div class="space-y-4">
            <div>
                <InputLabel
                    for="name"
                    value="Nama Lengkap"
                    class="text-text-main"
                />
                <BaseInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Contoh: John Doe"
                    required
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="email"
                    value="Email Address"
                    class="text-text-main"
                />
                <BaseInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    placeholder="john@example.com"
                    required
                />
                <InputError :message="form.errors.email" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="role"
                    value="Role / Hak Akses"
                    class="text-text-main"
                />
                <BaseSelect
                    id="role"
                    v-model="form.role"
                    :options="roles"
                    placeholder="Pilih Role User"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.role" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="password"
                    value="Password"
                    class="text-text-main"
                />
                <BaseInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    :placeholder="
                        user
                            ? 'Isi hanya jika ingin mengubah password'
                            : 'Masukkan password'
                    "
                    :required="!user"
                />
                <InputError :message="form.errors.password" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
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
                :disabled="form.processing"
            >
                {{ user ? "Simpan Perubahan" : "Buat User" }}
            </BaseButton>
        </div>
    </form>
</template>
