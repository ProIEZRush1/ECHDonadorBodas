<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AiUsageRecord extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'provider', 'model', 'input_tokens', 'output_tokens', 'cost_usd', 'metadata'];

    protected $casts = ['metadata' => 'array', 'cost_usd' => 'decimal:6'];
}
