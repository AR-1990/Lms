<?php

namespace App\Models\Concerns;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps each campus to its own records. A signed-in user who belongs to a campus sees that campus's rows plus
 * shared rows (no campus); head-office users (no campus, or `manage-campuses`) see every campus and may narrow
 * to one with the X-Campus-Id header (the campus picker in the top bar sends it). New rows take that campus.
 * Seeders, the scheduler and the queue run without a user, so they see everything.
 */
trait BelongsToCampus
{
    public static function bootBelongsToCampus(): void
    {
        static::addGlobalScope('campus', function (Builder $query): void {
            $campusId = self::activeCampusId();

            if ($campusId !== null) {
                $column = $query->getModel()->qualifyColumn('campus_id');
                $query->where(fn (Builder $inner) => $inner->where($column, $campusId)->orWhereNull($column));
            }
        });

        static::creating(function (Model $model): void {
            $model->campus_id ??= self::activeCampusId();
        });
    }

    /**
     * The campus the current request is limited to, or null for every campus.
     */
    public static function activeCampusId(): ?int
    {
        $request = request();
        $user = auth()->user();
        $memo = 'campus_scope:'.($user?->getAuthIdentifier() ?? 'guest');

        if ($request->attributes->has($memo)) {
            return $request->attributes->get($memo);
        }

        $campusId = null;

        if ($user !== null) {
            $seesAll = $user->campus_id === null || $user->hasPermission('manage-campuses');
            $picked = $request->header('X-Campus-Id');

            $campusId = $seesAll
                ? (is_numeric($picked) && Campus::whereKey((int) $picked)->exists() ? (int) $picked : null)
                : (int) $user->campus_id;
        }

        $request->attributes->set($memo, $campusId);

        return $campusId;
    }
}
