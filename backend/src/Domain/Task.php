<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Task
{
    /**
     * @param int $estimation duration in whole hours (> 0)
     */
    public function __construct(
        public int $id,
        public int $skillLevel,
        public int $estimation,
    ) {
    }
}
