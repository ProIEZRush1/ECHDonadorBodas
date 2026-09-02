<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    protected $attributes = [
        'brand_name' => 'Buy Overcloud',
        'brand_color' => '#6C5CE7',
        'status' => 'active',
        'timezone' => 'America/Mexico_City',
    ];

    protected $fillable = ['name', 'slug', 'brand_name', 'brand_color', 'status', 'timezone', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function whatsappConnections(): HasMany
    {
        return $this->hasMany(WhatsAppConnection::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function flows(): HasMany
    {
        return $this->hasMany(ConversationFlow::class);
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(BillingProfile::class);
    }
}
