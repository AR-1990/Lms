<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'scheduled_at', 'mode', 'venue_or_link', 'overall_rating', 'recommendation', 'feedback', 'attended'])]
class AdmissionInterview extends Model
{
    use LogsActivity, TracksAuthor;

    protected string $activityModule = 'admission_interviews';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'overall_rating' => 'integer', 'attended' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return 'Admission interview — application #'.$this->application_id;
    }
}
