<script setup>
import { ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";
import MembershipStatusBadge from "@/Components/Membership/MembershipStatusBadge.vue";
import { useConfirm } from "@/Composables/useConfirm";
import { usePermission } from "@/Composables/usePermission";

const props = defineProps({
    application: Object,
    canViewDocuments: Boolean,
    revisionCapabilityUrl: {
        type: String,
        default: null,
    },
});

const { confirm } = useConfirm();
const { can } = usePermission();
const reviewForm = useForm({
    staff_notes: props.application.staff_notes || "",
});
const rejectForm = useForm({
    rejection_reason: props.application.rejection_reason || "",
});
const revisionForm = useForm({
    revision_reason: props.application.revision_reason || "",
});
const processing = ref(false);
const copyState = ref("");

const documentLabel = (type) =>
    ({
        applicant_photo: "Foto pemohon",
        identity_document: "Dokumen identitas",
        payment_proof: "Bukti pembayaran",
    })[type] || type;

const statusLabel = (status) =>
    ({
        submitted: "Diterima",
        under_review: "Sedang ditinjau",
        revision_requested: "Revisi diminta",
        approved: "Disetujui",
        rejected: "Ditolak",
    })[status] || status;

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleString("id-ID");
};

const startReview = () => {
    reviewForm.post(route("admin.membership.applications.review", props.application.id), {
        preserveScroll: true,
    });
};

const approve = async () => {
    const ok = await confirm({
        title: "Approve membership application",
        message:
            "Approve membership application confirmation",
        confirmText: "Approve application",
        cancelText: "Cancel",
        variant: "warning",
    });

    if (!ok) return;

    processing.value = true;
    router.post(
        route("admin.membership.applications.approve", props.application.id),
        {},
        { onFinish: () => (processing.value = false) },
    );
};

const reject = async () => {
    const ok = await confirm({
        title: "Reject membership application",
        message: "Reject membership application confirmation",
        confirmText: "Reject application",
        cancelText: "Cancel",
        variant: "danger",
    });

    if (!ok) return;

    rejectForm.post(route("admin.membership.applications.reject", props.application.id), {
        preserveScroll: true,
    });
};

const requestRevision = async () => {
    const ok = await confirm({
        title: "Request membership application revision",
        message: "Request membership application revision confirmation",
        confirmText: "Request revision",
        cancelText: "Cancel",
        variant: "warning",
    });

    if (!ok) return;

    revisionForm.post(route("admin.membership.applications.revision", props.application.id), {
        preserveScroll: true,
    });
};

const copyRevisionLink = async () => {
    if (!props.revisionCapabilityUrl) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.revisionCapabilityUrl);
        copyState.value = "copied";
    } catch {
        copyState.value = "failed";
    }
};
</script>

<template>
    <AppLayout :title="$t('Membership application detail')">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-text-main leading-tight">
                        {{ application.legal_name }}
                    </h2>
                    <p class="text-sm text-text-muted font-mono mt-1">
                        {{ application.reference_code }}
                    </p>
                </div>
                <MembershipStatusBadge :status="application.status" />
            </div>
        </template>

        <div class="space-y-6">
            <BaseCard>
                <template #header>{{ $t("Applicant information") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Full legal name") }}</dt>
                        <dd class="mt-1 text-text-main font-medium">{{ application.legal_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Email") }}</dt>
                        <dd class="mt-1 text-text-main">{{ application.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Phone number") }}</dt>
                        <dd class="mt-1 text-text-main">{{ application.phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Submitted at") }}</dt>
                        <dd class="mt-1 text-text-main">{{ formatDate(application.submitted_at) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-text-muted">{{ $t("Address") }}</dt>
                        <dd class="mt-1 text-text-main whitespace-pre-wrap">{{ application.address }}</dd>
                    </div>
                    <div v-if="application.applicant_notes" class="sm:col-span-2">
                        <dt class="text-text-muted">{{ $t("Additional notes (optional)") }}</dt>
                        <dd class="mt-1 text-text-main whitespace-pre-wrap">{{ application.applicant_notes }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Documents") }}</template>
                <p v-if="!application.documents?.length" class="text-sm text-text-muted">
                    {{ $t("No documents attached") }}
                </p>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="document in application.documents"
                        :key="document.id"
                        class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                    >
                        <div>
                            <p class="text-sm font-medium text-text-main">
                                {{ documentLabel(document.document_type) }}
                            </p>
                            <p class="text-sm text-text-muted">{{ document.original_filename }}</p>
                        </div>
                        <a
                            v-if="canViewDocuments"
                            :href="route('admin.membership.documents.show', document.id)"
                            class="inline-flex items-center justify-center font-medium rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus min-h-control px-3 py-2 text-sm bg-surface border border-border text-text-main hover:bg-surface-variant"
                        >
                            {{ $t("Download") }}
                        </a>
                        <p v-else class="text-sm text-text-muted">
                            {{ $t("Document download requires additional permission") }}
                        </p>
                    </li>
                </ul>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Review and decision") }}</template>
                <div class="space-y-4">
                    <p class="text-sm text-text-muted">
                        {{ $t("Current status") }}:
                        <span class="font-medium text-text-main">{{ statusLabel(application.status) }}</span>
                    </p>

                    <div
                        v-if="revisionCapabilityUrl"
                        class="rounded-lg border border-border bg-surface-variant/40 p-4 space-y-3"
                    >
                        <p class="text-sm font-medium text-text-main">
                            {{ $t("Revision link shown once") }}
                        </p>
                        <p class="text-sm text-text-muted">
                            {{ $t("Revision link operational note") }}
                        </p>
                        <label class="sr-only" for="revision-capability-url">{{ $t("Revision link") }}</label>
                        <input
                            id="revision-capability-url"
                            :value="revisionCapabilityUrl"
                            readonly
                            class="border-border bg-surface text-text-main rounded-md shadow-sm block w-full text-sm font-mono"
                        />
                        <BaseButton type="button" variant="secondary" size="sm" @click="copyRevisionLink">
                            {{ $t("Copy revision link") }}
                        </BaseButton>
                        <p v-if="copyState === 'copied'" class="text-sm text-text-muted">
                            {{ $t("Revision link copied") }}
                        </p>
                        <p v-else-if="copyState === 'failed'" class="text-sm text-danger">
                            {{ $t("Revision link copy failed") }}
                        </p>
                    </div>

                    <div v-if="application.available_actions.includes('review')" class="space-y-3">
                        <BaseTextarea
                            id="staff_notes"
                            v-model="reviewForm.staff_notes"
                            :label="$t('Internal review notes')"
                            :hint="$t('Internal notes are not sent to the applicant')"
                            :error="reviewForm.errors.staff_notes"
                        />
                        <BaseButton
                            v-if="can('membership-applications.review')"
                            :loading="reviewForm.processing"
                            @click="startReview"
                        >
                            {{ $t("Start review") }}
                        </BaseButton>
                    </div>

                    <div
                        v-if="application.available_actions.includes('approve') || application.available_actions.includes('reject') || application.available_actions.includes('request_revision')"
                        class="space-y-4"
                    >
                        <BaseTextarea
                            v-if="application.available_actions.includes('reject')"
                            id="rejection_reason"
                            v-model="rejectForm.rejection_reason"
                            :label="$t('Rejection reason')"
                            :hint="$t('Rejection reason is optional and applicant-safe')"
                            :error="rejectForm.errors.rejection_reason"
                        />
                        <BaseTextarea
                            v-if="application.available_actions.includes('request_revision')"
                            id="revision_reason"
                            v-model="revisionForm.revision_reason"
                            :label="$t('Revision instruction')"
                            :hint="$t('Revision instruction is shown to the applicant')"
                            :error="revisionForm.errors.revision_reason"
                            :rows="4"
                        />
                        <div class="flex flex-col sm:flex-row gap-3">
                            <BaseButton
                                v-if="application.available_actions.includes('approve')"
                                :loading="processing"
                                @click="approve"
                            >
                                {{ $t("Approve application") }}
                            </BaseButton>
                            <BaseButton
                                v-if="application.available_actions.includes('request_revision')"
                                variant="secondary"
                                :loading="revisionForm.processing"
                                @click="requestRevision"
                            >
                                {{ $t("Request revision") }}
                            </BaseButton>
                            <BaseButton
                                v-if="application.available_actions.includes('reject')"
                                variant="danger"
                                :loading="rejectForm.processing"
                                @click="reject"
                            >
                                {{ $t("Reject application") }}
                            </BaseButton>
                        </div>
                    </div>

                    <div
                        v-if="application.available_actions.includes('reissue_revision')"
                        class="space-y-3"
                    >
                        <p class="text-sm text-text-muted">
                            {{ $t("Revision already requested") }}
                        </p>
                        <BaseTextarea
                            id="revision_reason_reissue"
                            v-model="revisionForm.revision_reason"
                            :label="$t('Revision instruction')"
                            :hint="$t('Reissue revision link hint')"
                            :error="revisionForm.errors.revision_reason"
                            :rows="4"
                        />
                        <BaseButton
                            variant="secondary"
                            :loading="revisionForm.processing"
                            @click="requestRevision"
                        >
                            {{ $t("Reissue revision link") }}
                        </BaseButton>
                    </div>

                    <div v-if="application.status === 'approved' && application.member_id">
                        <BaseButton
                            :href="route('admin.membership.members.show', application.member_id)"
                            variant="secondary"
                        >
                            {{ $t("View member record") }}
                        </BaseButton>
                    </div>

                    <dl v-if="application.approved_at || application.rejected_at" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm pt-2">
                        <div v-if="application.approved_at">
                            <dt class="text-text-muted">{{ $t("Approved at") }}</dt>
                            <dd class="mt-1">{{ formatDate(application.approved_at) }} · {{ application.approved_by_name }}</dd>
                        </div>
                        <div v-if="application.rejected_at">
                            <dt class="text-text-muted">{{ $t("Rejected at") }}</dt>
                            <dd class="mt-1">{{ formatDate(application.rejected_at) }} · {{ application.rejected_by_name }}</dd>
                        </div>
                        <div v-if="application.rejection_reason" class="sm:col-span-2">
                            <dt class="text-text-muted">{{ $t("Rejection reason") }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap">{{ application.rejection_reason }}</dd>
                        </div>
                        <div v-if="application.revision_reason" class="sm:col-span-2">
                            <dt class="text-text-muted">{{ $t("Revision instruction") }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap">{{ application.revision_reason }}</dd>
                        </div>
                        <div v-if="application.staff_notes" class="sm:col-span-2">
                            <dt class="text-text-muted">{{ $t("Internal review notes") }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap">{{ application.staff_notes }}</dd>
                        </div>
                    </dl>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("History") }}</template>
                <ol class="space-y-3">
                    <li
                        v-for="item in application.history"
                        :key="item.id"
                        class="text-sm border-l-2 border-border pl-3"
                    >
                        <p class="font-medium text-text-main">
                            {{ statusLabel(item.from_status) }} → {{ statusLabel(item.to_status) }}
                        </p>
                        <p class="text-text-muted">
                            {{ formatDate(item.created_at) }}
                            <span v-if="item.actor_name"> · {{ item.actor_name }}</span>
                            <span v-else> · {{ $t("Applicant") }}</span>
                        </p>
                        <p v-if="item.reason" class="mt-1 text-text-muted">{{ item.reason }}</p>
                    </li>
                </ol>
            </BaseCard>

            <Link
                :href="route('admin.membership.applications.index')"
                class="text-sm text-primary hover:underline"
            >
                {{ $t("Back to application list") }}
            </Link>
        </div>
    </AppLayout>
</template>
