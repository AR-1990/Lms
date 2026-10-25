<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A numbered certificate issued to a student (bonafide, character).
 */
#[Fillable(['student_id', 'certificate_type', 'certificate_no', 'issued_on', 'purpose', 'conduct_id', 'remarks'])]
class StudentCertificate extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'students';

    /**
     * @var array<string, string>
     */
    public const TYPES = ['bonafide' => 'Bonafide Certificate', 'character' => 'Character Certificate'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function conduct(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'conduct_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
