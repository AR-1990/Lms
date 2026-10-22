<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'scheduled_at', 'venue', 'subject_id', 'max_marks', 'obtained_marks', 'result', 'examiner_id', 'remarks'])]
class AdmissionTest extends Model
{
    use LogsActivity, TracksAuthor;

    protected string $activityModule = 'admission_tests';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'max_marks' => 'integer', 'obtained_marks' => 'decimal:2'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function activityLabel(): string
    {
        return 'Entry test — application #'.$this->application_id;
    }
}
