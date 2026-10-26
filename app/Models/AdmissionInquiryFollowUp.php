<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One contact with an enquiring family: how, what came of it, and the next follow-up date.
 */
#[Fillable(['admission_inquiry_id', 'followed_up_on', 'channel_id', 'outcome', 'next_follow_up_on'])]
class AdmissionInquiryFollowUp extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'admissions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['followed_up_on' => 'date:Y-m-d', 'next_follow_up_on' => 'date:Y-m-d'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
