<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConnection extends Model
{
    protected $table = 'whatsapp_connections';

    protected $fillable = ['organization_id', 'name', 'waba_id', 'phone_number_id', 'display_phone', 'access_token', 'verify_token', 'status', 'connected_at'];

    protected $hidden = ['access_token', 'verify_token'];

    protected $casts = ['access_token' => 'encrypted', 'verify_token' => 'encrypted', 'connected_at' => 'datetime'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
