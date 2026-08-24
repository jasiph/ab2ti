<script setup>
import { computed, nextTick } from "vue";
import { useForm } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseFileInput from "@/Components/Base/BaseFileInput.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    revisionInstruction: {
        type: String,
        default: "",
    },
    application: {
        type: Object,
        required: true,
    },
    documentConstraints: {
        type: Object,
        default: () => ({
            maxKilobytes: 5120,
            mimes: {},
        }),
    },
});

const form = useForm({
    legal_name: props.application.legal_name || "",
    email: props.application.email || "",
    phone: props.application.phone || "",
    address: props.application.address || "",
    applicant_notes: props.application.applicant_notes || "",
    applicant_photo: null,
    identity_document: null,
    payment_proof: null,
});

const errorEntries = computed(() =>
    Object.entries(form.errors).filter(([, message]) => Boolean(message)),
);

const currentFilename = (type) =>
    (props.application.documents || []).find((item) => item.document_type === type)
        ?.original_filename || null;

const mimeHint = (type) => {
    const mimes = props.documentConstraints?.mimes?.[type] || [];
    const maxKb = props.documentConstraints?.maxKilobytes || 5120;
    const maxMb = Math.round(maxKb / 1024);
    const readable = mimes
        .map((item) => String(item).toUpperCase())
        .filter((item, index, all) => all.indexOf(item) === index)
        .join(", ");
    const current = currentFilename(type);
    const base = readable
        ? `Opsional. ${readable}. Maksimal ${maxMb} MB.`
        : `Opsional. Maksimal ${maxMb} MB.`;

    return current ? `${base} Saat ini: ${current}` : base;
};

const acceptFor = (type) => {
    const mimes = props.documentConstraints?.mimes?.[type] || [];
    const map = {
        jpg: ".jpg,image/jpeg",
        jpeg: ".jpeg,image/jpeg",
        png: ".png,image/png",
        webp: ".webp,image/webp",
        pdf: ".pdf,application/pdf",
    };

    return [...new Set(mimes.flatMap((item) => (map[item] || `.${item}`).split(",")))].join(",");
};

const submit = () => {
    if (form.processing) {
        return;
    }

    form.post(route("capability.membership.revision.update"), {
        forceFormData: true,
        onError: () => {
            nextTick(() => {
                const invalid = document.querySelector("[aria-invalid='true']");
                if (invalid instanceof HTMLElement) {
                    invalid.focus();
                    return;
                }

                document.getElementById("revision-errors")?.focus();
            });
        },
    });
};

const onFile = (field, file) => {
    form[field] = file;
};
</script>

<template>
    <FrontendLayout :title="$t('Revise membership application')">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
            <h1 class="text-2xl sm:text-3xl font-bold text-text-main leading-tight">
                {{ $t("Revise membership application") }}
            </h1>
            <p class="mt-3 text-base text-text-muted leading-relaxed">
                {{ $t("Revise application intro") }}
            </p>

            <div class="mt-6 rounded-lg border border-border bg-surface p-4">
                <p class="text-sm font-medium text-text-main">
                    {{ $t("Revision instruction") }}
                </p>
                <p class="mt-2 text-sm text-text-muted whitespace-pre-wrap">
                    {{ revisionInstruction || $t("Please update the requested information") }}
                </p>
            </div>

            <div
                v-if="errorEntries.length"
                id="revision-errors"
                class="mt-6 rounded-lg border border-danger/40 bg-danger/5 p-4"
                role="alert"
                tabindex="-1"
            >
                <p class="font-medium text-text-main">
                    {{ $t("Please check the highlighted fields") }}
                </p>
                <ul class="mt-2 list-disc list-inside text-sm text-danger space-y-1">
                    <li v-for="[field, message] in errorEntries" :key="field">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <form
                class="mt-8 space-y-8"
                novalidate
                :aria-busy="form.processing ? 'true' : undefined"
                @submit.prevent="submit"
            >
                <fieldset class="space-y-5">
                    <legend class="text-base font-semibold text-text-main">
                        {{ $t("Applicant information") }}
                    </legend>

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
                        inputmode="email"
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
                        inputmode="tel"
                        maxlength="32"
                    />
                    <BaseTextarea
                        id="address"
                        v-model="form.address"
                        :label="$t('Address')"
                        :error="form.errors.address"
                        :rows="3"
                        required
                        autocomplete="street-address"
                        maxlength="2000"
                    />
                    <BaseTextarea
                        id="applicant_notes"
                        v-model="form.applicant_notes"
                        :label="$t('Additional notes (optional)')"
                        :error="form.errors.applicant_notes"
                        :rows="3"
                        maxlength="2000"
                    />
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="text-base font-semibold text-text-main">
                        {{ $t("Supporting documents (optional)") }}
                    </legend>
                    <p class="text-sm text-text-muted">
                        {{ $t("Replace documents only if requested") }}
                    </p>
                    <BaseFileInput
                        id="applicant_photo"
                        :label="$t('Applicant photo')"
                        :hint="mimeHint('applicant_photo')"
                        :error="form.errors.applicant_photo"
                        :accept="acceptFor('applicant_photo')"
                        @change="onFile('applicant_photo', $event)"
                    />
                    <BaseFileInput
                        id="identity_document"
                        :label="$t('Identity document')"
                        :hint="mimeHint('identity_document')"
                        :error="form.errors.identity_document"
                        :accept="acceptFor('identity_document')"
                        @change="onFile('identity_document', $event)"
                    />
                    <BaseFileInput
                        id="payment_proof"
                        :label="$t('Payment proof')"
                        :hint="mimeHint('payment_proof')"
                        :error="form.errors.payment_proof"
                        :accept="acceptFor('payment_proof')"
                        @change="onFile('payment_proof', $event)"
                    />
                </fieldset>

                <p v-if="form.processing" class="sr-only" role="status">
                    {{ $t("Sending application") }}
                </p>

                <BaseButton type="submit" size="lg" class="w-full sm:w-auto" :loading="form.processing">
                    {{ form.processing ? $t("Sending application") : $t("Resubmit application") }}
                </BaseButton>
            </form>
        </div>
    </FrontendLayout>
</template>
