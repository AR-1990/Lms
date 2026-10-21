<?php

namespace App\Services;

use App\Models\ActivityLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogger
{
    private const HIDDEN_VALUE = '[hidden]';

    /**
     * Columns that change on every write and say nothing useful to a reader.
     *
     * @var array<int, string>
     */
    private const IGNORED_FIELDS = ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'created_by', 'updated_by'];

    /**
     * Record a business action (approve, post, print, login…) with optional field changes.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     * @param  Model|null  $actor  who did it, when not the current request user (e.g. right after token login)
     */
    public function record(string $module, string $action, string $description, ?Model $subject = null, array $changes = [], ?Model $actor = null): ActivityLog
    {
        $user = $actor ?? request()->user();

        $log = ActivityLog::create([
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
            'module' => $module,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject && method_exists($subject, 'activityLabel') ? Str::limit($subject->activityLabel(), 250) : null,
            'description' => Str::limit($description, 497),
            'platform' => $this->platform(),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : Str::limit((string) request()->userAgent(), 250),
            'route' => app()->runningInConsole() ? null : request()->route()?->getName(),
            'created_at' => now(),
        ]);

        if ($changes !== []) {
            $log->changes()->createMany(collect($changes)->map(fn (array $change, string $field): array => [
                'field' => $field,
                'old_value' => $this->stringify($change['old']),
                'new_value' => $this->stringify($change['new']),
            ])->values()->all());
        }

        return $log;
    }

    /**
     * Record a create / update / delete / restore event coming from the LogsActivity trait.
     */
    public function recordModelEvent(Model $model, string $action): void
    {
        $changes = $this->modelChanges($model, $action);

        if ($action === 'updated' && $changes === []) {
            return;
        }

        $module = $model->activityModule();
        $actor = request()->user()?->name ?? 'System';
        $description = sprintf('%s %s %s "%s"', $actor, $action, Str::headline(Str::singular($module)), $model->activityLabel());

        $this->record($module, $action, $description, $model, $changes);
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function modelChanges(Model $model, string $action): array
    {
        $hiddenFields = [...$model->getHidden(), 'password'];

        $fields = match ($action) {
            'created' => array_keys($model->getAttributes()),
            'updated' => array_keys($model->getChanges()),
            default => [],
        };

        return collect($fields)
            ->reject(fn (string $field): bool => in_array($field, [...self::IGNORED_FIELDS, $model->getKeyName()], true))
            ->mapWithKeys(fn (string $field): array => [$field => [
                'old' => $action === 'created' ? null : $model->getOriginal($field),
                'new' => $model->getAttribute($field),
            ]])
            ->map(fn (array $change, string $field): array => in_array($field, $hiddenFields, true)
                ? ['old' => $change['old'] === null ? null : self::HIDDEN_VALUE, 'new' => self::HIDDEN_VALUE]
                : $change)
            ->all();
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            default => '[complex value]',
        };
    }

    private function platform(): string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'system';
        }

        $declared = strtolower((string) request()->header('X-Platform'));

        if (in_array($declared, ['android', 'ios', 'web'], true)) {
            return $declared;
        }

        return request()->is('api/*') ? 'api' : 'web';
    }
}
