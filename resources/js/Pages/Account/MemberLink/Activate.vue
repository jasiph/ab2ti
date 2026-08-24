<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";

const props = defineProps({
    secret: { type: String, required: true },
    member: { type: Object, required: true },
    mode: { type: String, default: "activate" },
    intended_email: { type: String, default: null },
    existing_account: { type: Boolean, default: false },
    suggested_name: { type: String, default: "" },
    login_url: { type: String, required: true },
});

const emailLocked = Boolean(props.intended_email);

const form = useForm({
    name: props.suggested_name || "",
    email: props.intended_email || "",
    password: "",
    password_confirmation: "",
});

const activate = () => {
    if (form.processing) {
        return;
    }

    form.post(route("account.member-link.activate", props.secret), {
        preserveScroll: true,
    });
};
</script>

<template>
    <FrontendLayout :title="$t('Member site access invitation')">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-text-main">
                    {{ $t("Member site access invitation") }}
                </h1>
                <p class="mt-3 text-text-muted leading-relaxed">
                    {{ $t("Member site access invitation intro") }}
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

            <BaseCard v-if="mode === 'login' || existing_account">
                <template #header>{{ $t("Account already exists") }}</template>
                <p class="text-sm text-text-muted leading-relaxed">
                    {{ $t("Account already exists help") }}
                </p>
                <p v-if="intended_email" class="mt-3 text-sm text-text-main">
                    {{ $t("Site account email") }}: {{ intended_email }}
                </p>
                <div class="mt-4">
                    <BaseButton :href="login_url">
                        {{ $t("Log in to connect") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <form
                v-else
                class="space-y-6"
                novalidate
                @submit.prevent="activate"
            >
                <BaseCard>
                    <template #header>{{ $t("Activate account") }}</template>
                    <div class="space-y-4">
                        <BaseInput
                            id="activation-name"
                            v-model="form.name"
                            :label="$t('Account name')"
                            :error="form.errors.name"
                            required
                            autocomplete="name"
                            maxlength="255"
                        />
                        <BaseInput
                            id="activation-email"
                            v-model="form.email"
                            type="email"
                            :label="$t('Site account email')"
                            :error="form.errors.email"
                            required
                            autocomplete="username"
                            maxlength="255"
                            :readonly="emailLocked"
                        />
                        <BaseInput
                            id="activation-password"
                            v-model="form.password"
                            type="password"
                            :label="$t('Password')"
                            :error="form.errors.password"
                            required
                            autocomplete="new-password"
                        />
                        <BaseInput
                            id="activation-password-confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            :label="$t('Confirm password')"
                            :error="form.errors.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                    </div>
                </BaseCard>

                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <BaseButton type="submit" :loading="form.processing">
                        {{ $t("Activate my access") }}
                    </BaseButton>
                    <Link
                        :href="login_url"
                        class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm min-h-control inline-flex items-center"
                    >
                        {{ $t("Already have an account") }}
                    </Link>
                </div>
            </form>
        </div>
    </FrontendLayout>
</template>
