<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeIdentityClass;
use App\Enums\CooperativeMatchMethod;
use App\Models\CooperativeBusiness;
use App\Models\Member;
use App\Support\Cooperative\Import\MemberMatcher;
use App\Support\Cooperative\Import\RowNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CooperativeMemberMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_email_match(): void
    {
        $member = Member::factory()->create(['email' => 'sari.utami@example.test']);

        $result = (new MemberMatcher)->match([
            'email' => 'sari.utami@example.test',
            'name' => 'Orang Lain',
        ]);

        $this->assertSame($member->id, $result['member_id']);
        $this->assertSame(CooperativeMatchMethod::EmailExact, $result['method']);
    }

    public function test_ab2ti_identifier_matches_membership_number(): void
    {
        $member = Member::factory()->create(['membership_number' => 'AB2TI/08/I/0005']);

        $result = (new MemberMatcher)->match([
            'identity_class' => CooperativeIdentityClass::Ab2tiIdentifier->value,
            'identity_value' => 'AB2TI/08/I/0005',
        ]);

        $this->assertSame($member->id, $result['member_id']);
        $this->assertSame(CooperativeMatchMethod::MemberIdentifierExact, $result['method']);
    }

    public function test_phone_match_uses_normalized_value(): void
    {
        $member = Member::factory()->create(['phone' => '081234567890']);

        $result = (new MemberMatcher)->match([
            'phone_normalized' => '081234567890',
        ]);

        $this->assertSame($member->id, $result['member_id']);
        $this->assertSame(CooperativeMatchMethod::PhoneExact, $result['method']);
    }

    public function test_valid_nik_does_not_auto_bind_because_member_has_no_nik(): void
    {
        Member::factory()->create(['legal_name' => 'Sari Utami']);

        $result = (new MemberMatcher)->match([
            'identity_class' => CooperativeIdentityClass::ValidNik->value,
            'identity_value' => '3174010101900001',
            'name' => 'Sari Utami',
        ]);

        $this->assertNull($result['member_id']);
        $this->assertSame(CooperativeMatchMethod::Unmatched, $result['method']);
    }

    public function test_ambiguous_email_requires_review(): void
    {
        Member::factory()->create(['email' => 'sama@example.test']);
        Member::factory()->create(['email' => 'sama@example.test']);

        $result = (new MemberMatcher)->match([
            'email' => 'sama@example.test',
        ]);

        $this->assertNull($result['member_id']);
        $this->assertSame(CooperativeMatchMethod::Ambiguous, $result['method']);
        $this->assertCount(2, $result['candidate_ids']);
    }

    public function test_unmatched_person_is_not_auto_created(): void
    {
        $result = (new MemberMatcher)->match([
            'name' => 'Tidak Ada',
            'email' => 'tidak.ada@example.test',
        ]);

        $this->assertNull($result['member_id']);
        $this->assertSame(CooperativeMatchMethod::Unmatched, $result['method']);
        $this->assertSame(0, Member::query()->where('email', 'tidak.ada@example.test')->count());
    }

    public function test_name_only_does_not_auto_bind_even_when_unique(): void
    {
        Member::factory()->create(['legal_name' => 'Sari Utami']);

        $result = (new MemberMatcher)->match([
            'name' => 'Sari Utami',
        ]);

        $this->assertNull($result['member_id']);
        $this->assertSame(CooperativeMatchMethod::Unmatched, $result['method']);
    }

    public function test_inferred_units_are_labeled_and_mismatch_needs_review(): void
    {
        $member = Member::factory()->create(['email' => 'anton.setiawan@example.test']);
        $business = CooperativeBusiness::psn();
        $normalizer = new RowNormalizer;

        $inferred = $normalizer->normalize([
            'name' => 'Anton Setiawan',
            'email' => 'anton.setiawan@example.test',
            'total' => '1.000.000',
            'share' => '33-000231',
        ], $business);

        $this->assertSame($member->id, $inferred['matched_member_id']);
        $this->assertSame(1, $inferred['normalized']['inferred_units']);
        $this->assertNull($inferred['normalized']['explicit_units']);
        $this->assertSame('inferred', $inferred['normalized']['units_origin']);

        Member::factory()->create(['email' => 'sari.utami@example.test']);
        $mismatch = $normalizer->normalize([
            'name' => 'Sari Utami',
            'email' => 'sari.utami@example.test',
            'total' => '50.000.000',
            'share' => '32-1-000181 (40)',
        ], $business);

        $this->assertContains('share_unit_mismatch', $mismatch['errors']);
        $this->assertFalse($mismatch['normalized']['share_reconciled']);
    }
}
