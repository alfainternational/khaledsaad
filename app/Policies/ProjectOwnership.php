<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * عزل مساحات العمل. كل صلاحية على أي كيان تابع للمشروع تنتهي هنا،
 * فلا يوجد مسار وصول ثانٍ يمكن أن ينسى التحقق.
 */
final class ProjectOwnership
{
    public static function owns(User $user, ?Project $project): bool
    {
        return $project !== null
            && $project->workspace()->where('owner_id', $user->id)->exists();
    }
}
