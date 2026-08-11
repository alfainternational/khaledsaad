<?php

namespace App\Models;

use App\Support\Experience\Experience;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'locale',
    'initial_experience', 'active_experience',
    'business_experience_enabled_at', 'learning_experience_enabled_at',
    'experience_selected_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_email_enabled' => 'boolean',
            'initial_experience' => Experience::class,
            'active_experience' => Experience::class,
            'business_experience_enabled_at' => 'datetime',
            'learning_experience_enabled_at' => 'datetime',
            'experience_selected_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function hasBusinessExperience(): bool
    {
        return $this->isAdmin() || $this->business_experience_enabled_at !== null;
    }

    public function hasLearningExperience(): bool
    {
        return $this->isAdmin() || $this->learning_experience_enabled_at !== null;
    }

    public function activeExperience(): ?Experience
    {
        return $this->active_experience;
    }

    public function canActivateExperience(Experience $experience): bool
    {
        return match ($experience) {
            Experience::BUSINESS => ! $this->hasBusinessExperience(),
            Experience::LEARNING => ! $this->hasLearningExperience(),
        };
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * BR-001: كل مشروع يتبع مساحة عمل واحدة. تُنشأ تلقائيًا عند أول حاجة
     * حتى لا يواجه المستخدم خطوة إعداد لا معنى لها في حساب فردي.
     */
    public function primaryWorkspace(): Workspace
    {
        return $this->workspaces()->oldest('id')->first()
            ?? $this->workspaces()->create([
                'name' => "مساحة {$this->name}",
                'slug' => Str::lower(Str::random(12)),
                'type' => 'personal',
            ]);
    }
}
