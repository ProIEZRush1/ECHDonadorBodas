<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Contact model for raffle participants.
 */
class Contact extends Model
{
    protected $fillable = [
        'nombre',
        'apellido_paterno',
        'nombre_completo',
        'telefono',
        'wa_id',
        'email',
        'status',
        'boletos',
        'datos_extra',
        'notas',
        'pais',
        'ultimo_mensaje_status',
        'ultimo_contacto',
    ];

    protected $casts = [
        'datos_extra' => 'array',
        'ultimo_contacto' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function conversationState(): HasOne
    {
        return $this->hasOne(ConversationState::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * Get display name for the contact.
     */
    public function getNombreDisplayAttribute(): string
    {
        return $this->nombre_completo ?? $this->nombre ?? 'Amigo';
    }

    /**
     * Detect country from phone number prefix.
     */
    public static function detectCountry(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+52') || str_starts_with($phone, '52')) {
            return 'MX';
        }
        if (str_starts_with($phone, '+1') || str_starts_with($phone, '1')) {
            return 'US';
        }
        if (str_starts_with($phone, '+972') || str_starts_with($phone, '972')) {
            return 'IL';
        }

        return 'MX';
    }
}
