<?php

declare(strict_types=1);

namespace App\Optimizer;

final class InvalidInputException extends \InvalidArgumentException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid input: '.implode('; ', $errors));
    }
}
