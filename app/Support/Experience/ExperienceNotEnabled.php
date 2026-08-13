<?php

namespace App\Support\Experience;

use DomainException;

class ExperienceNotEnabled extends DomainException
{
    public function __construct(public readonly Experience $experience)
    {
        parent::__construct("Experience [{$experience->value}] is not enabled.");
    }
}
