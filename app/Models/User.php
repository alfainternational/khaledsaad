<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
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
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
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
