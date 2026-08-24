<script setup>
import { useForm } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";

const props = defineProps({
    secret: { type: String, required: true },
    member: { type: Object, required: true },
    account_name: { type: String, required: true },
    account_email: { type: String, required: true },
    staff_blocked: { type: Boolean, default: false },
    already_linked_elsewhere: { type: Boolean, default: false },
});

const form = useForm({});

const blocked = props.staff_blocked || props.already_linked_elsewhere;

const confirm = () => {
    if (blocked || form.processing) {
        return;
    }

    form.post(route("account.member-link.confirm", props.secret));
};
</script>

<template>
    <FrontendLayout :title="$t('Connect my account')">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-text-main">
                    {{ $t("Connect my account") }}
                </h1>
                <p class="mt-3 text-text-muted leading-relaxed">
                    {{ $t("Connect account intro") }}
                </p>
            </div>

            <BaseCard>
                <template #header>{{ $t("Membership record") }}</template>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Member name") }}</dt>
                        <dd class="mt-1 font-medium text-text-main">{{ member.legal_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Record identifier") }}</dt>
                        <dd class="mt-1 font-mono">{{ member.membership_number }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Account to connect") }}</template>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Account name") }}</dt>
                        <dd class="mt-1 font-medium text-text-main">{{ account_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Account email") }}</dt>
                        <dd class="mt-1">{{ account_email }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <p
                v-if="staff_blocked"
                class="rounded-lg border border-danger/40 bg-danger/5 p-4 text-sm text-text-main"
                role="alert"
            >
                {{ $t("Staff cannot connect membership") }}
            </p>
            <p
                v-else-if="already_linked_elsewhere"
                class="rounded-lg border border-danger/40 bg-danger/5 p-4 text-sm text-text-main"
                role="alert"
            >
                {{ $t("Account already linked elsewhere") }}
            </p>

            <form v-else class="flex flex-col sm:flex-row gap-3" @submit.prevent="confirm">
                <BaseButton type="submit" :loading="form.processing">
                    {{ $t("Connect my account") }}
                </BaseButton>
            </form>
        </div>
    </FrontendLayout>
</template>
