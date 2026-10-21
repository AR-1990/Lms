<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes every create / update / delete / restore of the model to the activity log.
 * Models may set `protected string $activityModule` and override activityLabel().
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => app(ActivityLogger::class)->recordModelEvent($model, 'created'));
        static::updated(fn (Model $model) => app(ActivityLogger::class)->recordModelEvent($model, 'updated'));
        static::deleted(fn (Model $model) => app(ActivityLogger::class)->recordModelEvent($model, 'deleted'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => app(ActivityLogger::class)->recordModelEvent($model, 'restored'));
        }
    }

    /**
     * Module key shown in the activity log (e.g. `lookups`, `users`).
     */
    public function activityModule(): string
    {
        return property_exists($this, 'activityModule')
            ? $this->activityModule
            : Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Human label for the record at the moment of the action.
     */
    public function activityLabel(): string
    {
        return (string) ($this->getAttribute('name') ?? $this->getAttribute('title') ?? '#'.$this->getKey());
    }
}
