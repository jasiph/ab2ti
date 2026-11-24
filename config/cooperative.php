<?php

return [
    'psn_code' => 'PSN',

    'imports_disk' => env('COOPERATIVE_IMPORTS_DISK', 'cooperative_imports'),
    'imports_directory' => '',
    'imports_max_kilobytes' => (int) env('COOPERATIVE_IMPORTS_MAX_KB', 10240),
    'imports_max_rows' => (int) env('COOPERATIVE_IMPORTS_MAX_ROWS', 5000),
    'imports_header_scan_rows' => (int) env('COOPERATIVE_IMPORTS_HEADER_SCAN_ROWS', 20),

    /*
     | Public /koperasi ratio of Cooperative members to all AB2TI Members is
     | only meaningful when the association registry is complete. Default
     | false: Cooperative-only legacy bootstrap must not imply 100%
     | organizational participation.
     */
    'ab2ti_member_registry_complete' => (bool) env('AB2TI_MEMBER_REGISTRY_COMPLETE', false),

    /*
     | Public PSN headline aggregates are only shown as final totals when
     | legacy share reconciliation is explicitly complete.
     */
    'psn_legacy_reconciliation_complete' => (bool) env('PSN_LEGACY_RECONCILIATION_COMPLETE', false),

    /*
     | Ordinary administrative PSN adjustments may not use a future effective
     | date. created_at remains the recording timestamp (Asia/Jakarta).
     */
    'disallow_future_psn_adjustment_dates' => true,
];
