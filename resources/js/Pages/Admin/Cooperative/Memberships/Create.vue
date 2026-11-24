<script setup>
import { ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    candidates: { type: Array, default: () => [] },
    filters: Object,
    statusValues: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || "");
const selectedMemberId = ref(null);

const form = useForm({
    member_id: "",
    status: "active",
    joined_at: "",
    ended_at: "",
    notes: "",
});

let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route("admin.cooperative.memberships.create"),
            { search: value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const selectMember = (member) => {
    selectedMemberId.value = member.id;
    form.member_id = member.id;
};

const submit = () => {
    form.post(route("admin.cooperative.memberships.store"));
};
</script>

<template>
    <AppLayout :title="$t('Establish Cooperative membership')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                {{ $t("Establish Cooperative membership") }}
            </h2>
        </template>

        <div class="space-y-6 max-w-3xl">
            <BaseCard>
                <template #header>{{ $t("AB2TI member") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Cooperative member selector help") }}
                </p>
                <BaseInput
                    id="cooperative-member-search"
                    v-model="search"
                    :label="$t('Search AB2TI members')"
                    :placeholder="$t('Name, email, or record identifier')"
                    :error="form.errors.member_id"
                />
                <ul v-if="candidates.length" class="mt-4 divide-y divide-border rounded-md border border-border">
                    <li v-for="member in candidates" :key="member.id">
                        <button
                            type="button"
                            class="w-full text-left px-4 py-3 hover:bg-surface-variant/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            :class="selectedMemberId === member.id ? 'bg-primary/5' : ''"
                            @click="selectMember(member)"
                        >
                            <span class="block font-medium text-text-main">{{ member.legal_name }}</span>
                            <span class="block font-mono text-xs text-text-muted">{{ member.membership_number }}</span>
                        </button>
                    </li>
                </ul>
                <p v-else-if="search" class="mt-4 text-sm text-text-muted">
                    {{ $t("No eligible AB2TI members found") }}
                </p>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Cooperative membership details") }}</template>
                <form class="space-y-4" novalidate @submit.prevent="submit">
                    <label class="block text-sm">
                        <span class="text-text-muted">{{ $t("Status") }}</span>
                        <select
                            v-model="form.status"
                            class="mt-1 w-full border-border bg-surface text-text-main rounded-md shadow-sm min-h-control text-sm"
                        >
                            <option value="active">{{ $t("Active") }}</option>
                            <option value="inactive">{{ $t("Inactive") }}</option>
                        </select>
                        <p v-if="form.errors.status" class="text-sm text-danger mt-1">{{ form.errors.status }}</p>
                    </label>
                    <BaseInput
                        id="joined_at"
                        v-model="form.joined_at"
                        type="date"
                        :label="$t('Joined at')"
                        :error="form.errors.joined_at"
                    />
                    <BaseInput
                        id="ended_at"
                        v-model="form.ended_at"
                        type="date"
                        :label="$t('Ended at')"
                        :error="form.errors.ended_at"
                    />
                    <BaseTextarea
                        id="notes"
                        v-model="form.notes"
                        :label="$t('Private staff notes')"
                        :error="form.errors.notes"
                        :rows="4"
                    />
                    <div class="flex flex-wrap gap-3">
                        <BaseButton type="submit" :loading="form.processing" :disabled="!form.member_id">
                            {{ $t("Save") }}
                        </BaseButton>
                        <BaseButton
                            :href="route('admin.cooperative.memberships.index')"
                            variant="secondary"
                        >
                            {{ $t("Cancel") }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>
        </div>
    </AppLayout>
</template>
