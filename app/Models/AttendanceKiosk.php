<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A gate device that scans ID cards without anyone signing in; its public link carries a secret token.
 */
#[Fillable(['name', 'token_hash', 'campus_id', 'is_active', 'last_used_at', 'scan_count'])]
#[Hidden(['token_hash'])]
class AttendanceKiosk extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'attendance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_used_at' => 'datetime', 'scan_count' => 'integer'];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))->where('is_active', true)->first();
    }
}
