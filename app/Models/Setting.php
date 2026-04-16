<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value settings store.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set a setting value.
     */
    public static function setValue(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
