<?php

namespace App\Exceptions\Cooperative;

use RuntimeException;

class StalePsnConfirmationException extends RuntimeException
{
    public function __construct(
        public readonly int $reviewedUnits,
        public readonly int $currentUnits,
    ) {
        parent::__construct(__('PSN shares changed since this page was opened. Review the latest share count before confirming.'));
    }

    /**
     * @return array{reviewed_units: int, current_units: int}
     */
    public function comparison(): array
    {
        return [
            'reviewed_units' => $this->reviewedUnits,
            'current_units' => $this->currentUnits,
        ];
    }
}
