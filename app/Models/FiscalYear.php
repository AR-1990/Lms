<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'starts_on', 'ends_on', 'is_current', 'status', 'closed_by', 'closed_at'])]
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use HasFactory, LogsActivity, TracksAuthor;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected string $activityModule = 'fiscal_years';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
