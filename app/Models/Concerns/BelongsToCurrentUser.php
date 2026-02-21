<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToCurrentUser
{
    protected static function bootBelongsToCurrentUser(): void
    {
        static::addGlobalScope('current_user', function (Builder $builder) {
            if (!Auth::check()) {
                return;
            }

            $tenantUserId = Auth::user()->tenantUserId();
            $table = $builder->getModel()->getTable();
            $builder->where($table . '.user_id', $tenantUserId);
        });

        static::creating(function ($model) {
            if (Auth::check() && empty($model->user_id)) {
                $model->user_id = Auth::user()->tenantUserId();
            }
        });
    }
}
