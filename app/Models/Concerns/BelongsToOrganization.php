<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            if (app()->bound('currentOrganization')) {
                $builder->where($builder->qualifyColumn('organization_id'), app('currentOrganization')->id);
            }
        });

        static::creating(function ($model): void {
            if (! $model->organization_id && app()->bound('currentOrganization')) {
                $model->organization_id = app('currentOrganization')->id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
