<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks donations/comprobantes for raffle ticket purchases.
 */
class Donation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'contact_id',
        'amount',
        'boletos',
        'reference',
        'receipt_media_id',
        'receipt_analysis',
        'status',
        'confidence',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'receipt_analysis' => 'array',
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
