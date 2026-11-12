<?php

declare(strict_types=1);

namespace App\Optimizer\Input;

final class InvalidInputException extends \InvalidArgumentException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid input: '.implode('; ', $errors));
    }
}
