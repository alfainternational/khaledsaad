<?php

namespace App\Domain\AI\Knowledge;

use RuntimeException;

class InvalidProjectKnowledgeData extends RuntimeException
{
    // Marker for invalid project records that batch synchronization may skip.
}
