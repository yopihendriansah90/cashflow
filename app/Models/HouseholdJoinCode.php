<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseholdJoinCode extends Model
{
    protected $fillable = ['created_by_membership_id', 'code_hash', 'expires_at', 'used_at', 'revoked_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
