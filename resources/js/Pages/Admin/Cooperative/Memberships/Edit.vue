<script setup>
import { useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    membership: Object,
    statusValues: { type: Array, default: () => [] },
});

const form = useForm({
    status: props.membership.status || "active",
    joined_at: props.membership.joined_at || "",
    ended_at: props.membership.ended_at || "",
    notes: props.membership.notes || "",
});

const submit = () => {
    form.patch(route("admin.cooperative.memberships.update", props.membership.id));
};
</script>

<template>
    <AppLayout :title="$t('Edit Cooperative membership')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                {{ $t("Edit Cooperative membership") }}
            </h2>
        </template>

        <div class="space-y-6 max-w-3xl">
            <BaseCard>
                <template #header>{{ $t("AB2TI member") }}</template>
                <p class="text-sm text-text-main font-medium">
                    {{ membership.member?.legal_name }}
                </p>
                <p class="text-sm text-text-muted font-mono">
                    {{ membership.member?.membership_number }}
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
                            <option
                                v-for="value in statusValues"
                                :key="value"
                                :value="value"
                            >
                                {{ value === "active" ? $t("Active") : $t("Inactive") }}
                            </option>
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
                        <BaseButton type="submit" :loading="form.processing">
                            {{ $t("Save") }}
                        </BaseButton>
                        <BaseButton
                            :href="route('admin.cooperative.memberships.show', membership.id)"
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
