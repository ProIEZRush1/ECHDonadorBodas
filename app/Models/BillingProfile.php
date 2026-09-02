<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingProfile extends Model
{
    protected $fillable = ['organization_id', 'stripe_customer_id', 'stripe_payment_method_id', 'card_brand', 'card_last_four', 'card_expiry', 'currency', 'spend_limit', 'status'];

    protected $hidden = ['stripe_payment_method_id'];

    protected $casts = ['spend_limit' => 'decimal:2'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
