<?php

namespace Tests\Unit\Database;

use Tests\TestCase;

class MysqlIdentifierLengthTest extends TestCase
{
    public function test_membership_transition_and_document_identifiers_are_explicit_and_fit_mysql(): void
    {
        $limit = 64;

        $transitions = (string) file_get_contents(database_path(
            'migrations/2026_08_20_140001_create_membership_application_transitions_table.php',
        ));
        $documents = (string) file_get_contents(database_path(
            'migrations/2026_08_20_140002_create_membership_documents_table.php',
        ));

        $this->assertStringContainsString("indexName: 'mat_application_fk'", $transitions);
        $this->assertStringContainsString("indexName: 'mat_actor_user_fk'", $transitions);
        $this->assertStringContainsString("'mat_application_created_idx'", $transitions);
        $this->assertStringContainsString("'md_application_type_idx'", $documents);

        foreach ([
            'mat_application_fk',
            'mat_actor_user_fk',
            'mat_application_created_idx',
            'md_application_type_idx',
        ] as $name) {
            $this->assertLessThanOrEqual($limit, strlen($name), $name);
        }

        $this->assertGreaterThan(
            $limit,
            strlen('membership_application_transitions_membership_application_id_foreign'),
        );
        $this->assertGreaterThan(
            $limit,
            strlen('membership_application_transitions_membership_application_id_created_at_index'),
        );
        $this->assertGreaterThan(
            $limit,
            strlen('membership_documents_membership_application_id_document_type_index'),
        );
    }

    public function test_lapak_foreign_key_and_index_names_are_explicit_and_fit_mysql(): void
    {
        $limit = 64;
        $migration = (string) file_get_contents(database_path(
            'migrations/2026_08_22_160000_create_lapak_tables.php',
        ));

        $this->assertStringContainsString("'lc_active_sort_idx'", $migration);
        $this->assertStringContainsString("indexName: 'll_lapak_fk'", $migration);
        $this->assertStringContainsString("indexName: 'll_category_fk'", $migration);
        $this->assertStringContainsString("'ll_lapak_slug_unique'", $migration);
        $this->assertStringContainsString("'ll_status_avail_idx'", $migration);

        foreach ([
            'lc_active_sort_idx',
            'll_lapak_fk',
            'll_category_fk',
            'll_lapak_slug_unique',
            'll_status_avail_idx',
            'lapaks_moderation_idx',
        ] as $name) {
            $this->assertLessThanOrEqual($limit, strlen($name), $name);
        }
    }
}
