<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeIdentityClass;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMemberResolution;
use App\Models\CooperativeImportRow;

final class SourceDuplicateAuditor
{
    /**
     * Mark source-internal identity collisions. NIK is compared only in staging.
     *
     * @param  iterable<CooperativeImportRow>  $rows
     * @return int Duplicate/conflict row count
     */
    public function apply(iterable $rows): int
    {
        $conflicts = $this->duplicateCodesByRowId($rows);

        foreach ($rows as $row) {
            $codes = array_values(array_unique($conflicts[$row->id] ?? []));
            if ($codes === []) {
                continue;
            }

            $data = $row->normalized_data ?? [];
            $identity = array_values(array_unique([
                ...($data['review_categories']['identity'] ?? []),
                ...$codes,
            ]));
            $data['review_categories']['identity'] = $identity;
            $data['member_resolution'] = CooperativeMemberResolution::Ambiguous->value;
            $data['propose_create_member'] = false;
            $data['propose_create_membership'] = false;
            $data['components']['member']['status'] = 'needs_review';
            $data['components']['cooperative_membership']['status'] = 'needs_review';

            $errors = array_values(array_unique([
                ...($row->validation_errors ?? []),
                ...$codes,
            ]));

            $row->forceFill([
                'normalized_data' => $data,
                'validation_status' => CooperativeImportRowStatus::NeedsReview,
                'validation_errors' => $errors,
                'matched_member_id' => null,
            ])->save();
        }

        return count($conflicts);
    }

    /**
     * @param  iterable<CooperativeImportRow>  $rows
     * @return array<int, list<string>>
     */
    public function duplicateCodesByRowId(iterable $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            foreach ($this->keys($row) as $key) {
                $indexed[$key][] = $row;
            }
        }

        $conflicts = [];

        foreach ($indexed as $key => $group) {
            if (count($group) < 2) {
                continue;
            }

            $code = str_starts_with($key, 'nik:')
                ? 'source_duplicate_nik'
                : (str_starts_with($key, 'ab2ti:')
                    ? 'source_duplicate_identifier'
                    : (str_starts_with($key, 'email:')
                        ? 'source_duplicate_email'
                        : 'source_duplicate_phone'));

            foreach ($group as $row) {
                $conflicts[$row->id][] = $code;
            }
        }

        return $conflicts;
    }

    /**
     * @return list<string>
     */
    private function keys(CooperativeImportRow $row): array
    {
        $data = $row->normalized_data ?? [];
        $keys = [];

        $class = (string) ($data['identity_class'] ?? '');
        $value = trim((string) ($data['identity_value'] ?? ''));

        if ($class === CooperativeIdentityClass::ValidNik->value && $value !== '') {
            $keys[] = 'nik:'.$value;
        }

        if ($class === CooperativeIdentityClass::Ab2tiIdentifier->value && $value !== '') {
            $keys[] = 'ab2ti:'.mb_strtolower($value);
        }

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $keys[] = 'email:'.$email;
        }

        $phone = trim((string) ($data['phone_normalized'] ?? ''));
        if ($phone !== '') {
            $keys[] = 'phone:'.$phone;
        }

        return $keys;
    }
}
