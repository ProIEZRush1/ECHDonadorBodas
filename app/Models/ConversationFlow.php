<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationFlow extends Model
{
    protected $fillable = ['organization_id', 'name', 'description', 'system_prompt', 'nodes', 'is_active', 'version'];

    protected $casts = ['nodes' => 'array', 'is_active' => 'boolean'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
