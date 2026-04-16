<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mass WhatsApp campaign for raffle promotion.
 */
class Campaign extends Model
{
    protected $fillable = [
        'name',
        'template_name',
        'status',
        'total_contacts',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->withPivot('wa_message_id', 'status')
            ->withTimestamps();
    }
}
