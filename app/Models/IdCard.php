<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'holder_type', 'holder_id', 'card_template_id', 'card_no', 'qr_token', 'academic_session_id', 'issued_on', 'valid_until',
    'status', 'replacement_of_id', 'replacement_fee_invoice_id', 'status_reason', 'print_count', 'last_printed_at',
])]
class IdCard extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Workflow: active → lost | revoked | expired; a lost card is reissued as a new card (old one becomes replaced).
     */
    public const STATUSES = ['active', 'lost', 'replaced', 'expired', 'revoked'];

    /**
     * holder_type value → model.
     *
     * @var array<string, class-string<Model>>
     */
    public const HOLDERS = ['student' => Student::class, 'employee' => Employee::class];

    protected string $activityModule = 'id_cards';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date', 'valid_until' => 'date', 'last_printed_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return $this->card_no;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'holder_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'holder_id');
    }

    /**
     * The student or employee this card belongs to (load the matching relation first).
     */
    public function holder(): Student|Employee|null
    {
        return $this->holder_type === 'employee' ? $this->employee : $this->student;
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'card_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
