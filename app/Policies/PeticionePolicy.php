<?php

namespace App\Policies;

use App\Models\Petitions;
use App\Models\User;

class PeticionePolicy
{
    public function update(User $user, Petitions $petition): bool
    {
        return $user->id === $petition->user_id;
    }

    public function delete(User $user, Petitions $petition): bool
    {
        return $user->id === $petition->user_id;
    }
    
}