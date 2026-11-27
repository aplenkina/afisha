<?php

declare(strict_types=1);

namespace App\Admin\Request\Validation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class OptionalDatabaseId extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\Length(max: 10),
            new Assert\Regex('/^[1-9][0-9]*$/D'),
            new Assert\LessThanOrEqual(2_147_483_647),
        ];
    }
}
