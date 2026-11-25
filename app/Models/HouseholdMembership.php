<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMembership extends Model
{
    public const OWNER = 'owner';

    public const PARTNER = 'partner';

    protected $fillable = ['user_id', 'role', 'ended_at'];

    protected function casts(): array
    {
        return ['ended_at' => 'datetime'];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
