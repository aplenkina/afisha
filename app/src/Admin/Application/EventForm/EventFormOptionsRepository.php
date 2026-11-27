<?php

declare(strict_types=1);

namespace App\Admin\Application\EventForm;

interface EventFormOptionsRepository
{
    public function options(): EventFormOptions;
}
