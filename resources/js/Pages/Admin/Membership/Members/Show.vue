<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";
import MembershipStatusBadge from "@/Components/Membership/MembershipStatusBadge.vue";
import CooperativeBadge from "@/Components/Cooperative/CooperativeBadge.vue";

const props = defineProps({
    member: Object,
    canUpdate: Boolean,
    canIssueAccountLink: Boolean,
    canViewSourceApplication: Boolean,
    canManageCooperative: Boolean,
    accountLinkInvitationUrl: {
        type: String,
        default: null,
    },
});

const form = useForm({
    legal_name: props.member.legal_name || "",
    email: props.member.email || "",
    phone: props.member.phone || "",
    address: props.member.address || "",
});
const issueForm = useForm({
    intended_email: props.member.email || "",
});
const revokeForm = useForm({});
const copyState = ref("");

const link = computed(() => props.member.account_link || {});

const siteAccessLabelKey = computed(() => {
    if (link.value.status === "linked") {
        return "Site access active";
    }
    if (link.value.status === "invited") {
        return "Site access invited";
    }
    if (link.value.status === "expired") {
        return link.value.revoked ? "Site access revoked" : "Site access expired";
    }

    return "Site access inactive";
});

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleString("id-ID");
};

const saveContact = () => {
    form.patch(route("admin.membership.members.update", props.member.id), {
        preserveScroll: true,
    });
};

const issueLink = () => {
    issueForm.post(route("admin.membership.members.account-link", props.member.id), {
        preserveScroll: true,
    });
};

const revokeLink = () => {
    revokeForm.delete(route("admin.membership.members.account-link.revoke", props.member.id), {
        preserveScroll: true,
    });
};

const copyInvitation = async () => {
    if (!props.accountLinkInvitationUrl) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.accountLinkInvitationUrl);
        copyState.value = "copied";
    } catch {
        copyState.value = "failed";
    }
};
</script>

<template>
    <AppLayout :title="$t('AB2TI member detail')">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-text-main leading-tight">
                        {{ member.legal_name }}
                    </h2>
                    <p class="text-sm text-text-muted mt-1">
                        {{ $t("Record identifier") }}:
                        <span class="font-mono">{{ member.membership_number }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <MembershipStatusBadge :status="member.status" />
                    <CooperativeBadge :state="member.cooperative?.state || 'none'" />
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <BaseCard>
                <template #header>{{ $t("Member data") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Record identifier is not Kartu Anggota number") }}
                </p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Record identifier") }}</dt>
                        <dd class="mt-1 font-mono text-text-main">{{ member.membership_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Recognized at") }}</dt>
                        <dd class="mt-1">{{ formatDate(member.recognized_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Created by") }}</dt>
                        <dd class="mt-1">{{ member.created_by_name || "—" }}</dd>
                    </div>
                </dl>
            </BaseCard>


            <BaseCard>
                <template #header>{{ $t("Cooperative membership") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Cooperative membership is optional") }}
                </p>
                <p class="text-sm text-text-main">
                    <template v-if="member.cooperative?.state === 'active'">{{ $t("Active") }}</template>
                    <template v-else-if="member.cooperative?.state === 'inactive'">{{ $t("Inactive") }}</template>
                    <template v-else>{{ $t("Not a Cooperative member") }}</template>
                </p>
                <div v-if="canManageCooperative" class="mt-4">
                    <BaseButton
                        v-if="member.cooperative?.id"
                        :href="route('admin.cooperative.memberships.show', member.cooperative.id)"
                        variant="secondary"
                    >
                        {{ $t("Open Cooperative membership") }}
                    </BaseButton>
                    <BaseButton
                        v-else
                        :href="route('admin.cooperative.memberships.create')"
                        variant="secondary"
                    >
                        {{ $t("Establish Cooperative membership") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Contact") }}</template>
                <form
                    v-if="canUpdate"
                    class="space-y-4"
                    novalidate
                    @submit.prevent="saveContact"
                >
                    <BaseInput
                        id="legal_name"
                        v-model="form.legal_name"
                        :label="$t('Full legal name')"
                        :error="form.errors.legal_name"
                        required
                        autocomplete="name"
                        maxlength="255"
                    />
                    <BaseInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        :label="$t('Email')"
                        :error="form.errors.email"
                        required
                        autocomplete="email"
                        maxlength="255"
                    />
                    <BaseInput
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        :label="$t('Phone number')"
                        :error="form.errors.phone"
                        required
                        autocomplete="tel"
                        maxlength="32"
                    />
                    <BaseTextarea
                        id="address"
                        v-model="form.address"
                        :label="$t('Address')"
                        :error="form.errors.address"
                        :rows="3"
                        required
                        maxlength="2000"
                    />
                    <BaseButton type="submit" :loading="form.processing">
                        {{ $t("Save contact") }}
                    </BaseButton>
                </form>
                <dl v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Full legal name") }}</dt>
                        <dd class="mt-1 font-medium text-text-main">{{ member.legal_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Email") }}</dt>
                        <dd class="mt-1">{{ member.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Phone number") }}</dt>
                        <dd class="mt-1">{{ member.phone }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-text-muted">{{ $t("Address") }}</dt>
                        <dd class="mt-1 whitespace-pre-wrap">{{ member.address }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Site access") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Site access") }}:
                    <span class="font-medium text-text-main">{{ $t(siteAccessLabelKey) }}</span>
                </p>
                <p v-if="link.status === 'unlinked'" class="text-sm text-text-muted mb-4">
                    {{ $t("Site access inactive help") }}
                </p>
                <p v-else-if="link.status === 'invited'" class="text-sm text-text-muted mb-4">
                    {{ $t("Site access invited help") }}
                </p>
                <p v-else-if="link.status === 'linked'" class="text-sm text-text-muted mb-4">
                    {{ $t("Site access linked help") }}
                </p>
                <p v-else-if="link.status === 'expired'" class="text-sm text-text-muted mb-4">
                    {{ $t("Site access expired help") }}
                </p>

                <dl v-if="link.status === 'linked'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <dt class="text-text-muted">{{ $t("Linked account") }}</dt>
                        <dd class="mt-1">{{ link.linked_user_name }} · {{ link.linked_user_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Linked at") }}</dt>
                        <dd class="mt-1">{{ formatDate(link.linked_at) }}</dd>
                    </div>
                </dl>
                <dl v-else-if="link.status === 'invited'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-4">
                    <div v-if="link.invitation_expires_at">
                        <dt class="text-text-muted">{{ $t("Invitation expires at") }}</dt>
                        <dd class="mt-1">{{ formatDate(link.invitation_expires_at) }}</dd>
                    </div>
                    <div v-if="link.intended_email">
                        <dt class="text-text-muted">{{ $t("Site account email") }}</dt>
                        <dd class="mt-1">{{ link.intended_email }}</dd>
                    </div>
                </dl>
                <p
                    v-if="link.status === 'invited' && link.existing_account"
                    class="text-sm text-text-muted mb-4"
                >
                    {{ $t("Account already exists") }}
                </p>

                <div
                    v-if="accountLinkInvitationUrl"
                    class="rounded-lg border border-border bg-surface-variant/40 p-4 space-y-3 mb-4"
                >
                    <p class="text-sm font-medium text-text-main">
                        {{ $t("Activation link shown once") }}
                    </p>
                    <p class="text-sm text-text-muted">
                        {{ $t("Activation link operational note") }}
                    </p>
                    <label class="sr-only" for="member-account-link-url">{{ $t("Activation link shown once") }}</label>
                    <input
                        id="member-account-link-url"
                        :value="accountLinkInvitationUrl"
                        readonly
                        class="border-border bg-surface text-text-main rounded-md shadow-sm block w-full text-sm font-mono"
                    />
                    <BaseButton type="button" variant="secondary" size="sm" @click="copyInvitation">
                        {{ $t("Copy activation link") }}
                    </BaseButton>
                    <p v-if="copyState === 'copied'" class="text-sm text-text-muted" role="status">
                        {{ $t("Activation link copied") }}
                    </p>
                    <p v-else-if="copyState === 'failed'" class="text-sm text-danger" role="alert">
                        {{ $t("Activation link copy failed") }}
                    </p>
                </div>

                <form
                    v-if="canIssueAccountLink && link.status !== 'linked'"
                    class="space-y-4"
                    novalidate
                    @submit.prevent="issueLink"
                >
                    <BaseInput
                        id="intended_email"
                        v-model="issueForm.intended_email"
                        type="email"
                        :label="$t('Site account email')"
                        :hint="$t('Site account email hint')"
                        :error="issueForm.errors.intended_email"
                        required
                        autocomplete="off"
                        maxlength="255"
                    />
                    <div class="flex flex-wrap gap-2">
                        <BaseButton type="submit" :loading="issueForm.processing">
                            {{
                                link.status === "invited"
                                    ? $t("Reissue invitation")
                                    : $t("Grant site access")
                            }}
                        </BaseButton>
                        <BaseButton
                            v-if="link.status === 'invited'"
                            type="button"
                            variant="secondary"
                            :loading="revokeForm.processing"
                            @click="revokeLink"
                        >
                            {{ $t("Revoke invitation") }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Source membership application") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Source application is not the member record") }}
                </p>
                <p v-if="!member.source_application" class="text-sm text-text-muted">
                    {{ $t("No source application") }}
                </p>
                <dl v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Application reference") }}</dt>
                        <dd class="mt-1 font-mono">{{ member.source_application.reference_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Current status") }}</dt>
                        <dd class="mt-1">
                            <MembershipStatusBadge :status="member.source_application.status" />
                        </dd>
                    </div>
                    <div v-if="canViewSourceApplication" class="sm:col-span-2">
                        <BaseButton
                            :href="route('admin.membership.applications.show', member.source_application.id)"
                            variant="secondary"
                        >
                            {{ $t("Open source application") }}
                        </BaseButton>
                    </div>
                </dl>
            </BaseCard>

            <Link
                :href="route('admin.membership.members.index')"
                class="text-sm text-primary hover:underline"
            >
                {{ $t("Back to member list") }}
            </Link>
        </div>
    </AppLayout>
</template>
