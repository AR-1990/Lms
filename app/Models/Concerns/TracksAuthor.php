<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Fills the `created_by` / `updated_by` audit columns from the signed-in user.
 */
trait TracksAuthor
{
    public static function bootTracksAuthor(): void
    {
        static::creating(function (Model $model): void {
            $model->created_by ??= auth()->id();
            $model->updated_by ??= auth()->id();
        });

        static::updating(function (Model $model): void {
            $model->updated_by = auth()->id() ?? $model->updated_by;
        });
    }
}
