<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks AI conversation state per contact.
 */
class ConversationState extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'contact_id',
        'current_step',
        'collected_data',
        'ai_context',
        'last_interaction',
        'expires_at',
    ];

    protected $casts = [
        'collected_data' => 'array',
        'ai_context' => 'array',
        'last_interaction' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
