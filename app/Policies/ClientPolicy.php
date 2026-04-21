<?php

namespace App\Policies;

use App\Domain\Client\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        return $this->role($user, $client) !== null;
    }

    public function update(User $user, Client $client): bool
    {
        return in_array($this->role($user, $client), ['owner', 'admin', 'editor'], true);
    }

    public function delete(User $user, Client $client): bool
    {
        return in_array($this->role($user, $client), ['owner', 'admin'], true);
    }

    private function role(User $user, Client $client): ?string
    {
        return $client->workspace?->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');
    }
}
