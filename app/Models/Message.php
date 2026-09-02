<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message log for WhatsApp conversations.
 */
class Message extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'contact_id',
        'direction',
        'content',
        'wa_message_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
