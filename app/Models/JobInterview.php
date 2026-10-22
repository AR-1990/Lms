<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_application_id', 'round_no', 'round_type_id', 'scheduled_at', 'mode', 'venue_or_link', 'status', 'overall_score', 'recommendation', 'summary'])]
class JobInterview extends Model
{
    use LogsActivity, TracksAuthor;

    protected string $activityModule = 'job_interviews';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'round_no' => 'integer', 'overall_score' => 'decimal:1'];
    }

    public function roundType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'round_type_id');
    }

    public function activityLabel(): string
    {
        return 'Round '.$this->round_no.' — application #'.$this->job_application_id;
    }
}
