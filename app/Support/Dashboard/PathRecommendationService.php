<?php

namespace App\Support\Dashboard;

class PathRecommendationService
{
    public function recommend(?string $persona, ?string $goal, ?string $awareness): string
    {
        return PathCatalog::recommend($persona, $goal, $awareness);
    }
}
