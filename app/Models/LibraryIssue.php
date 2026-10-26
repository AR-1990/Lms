<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A copy lent to a student or staff member: issued → returned (late fine worked out) or lost.
 */
#[Fillable(['library_copy_id', 'borrower_type', 'borrower_id', 'issued_on', 'due_on', 'returned_on', 'fine_amount', 'fee_invoice_id', 'status', 'remarks'])]
class LibraryIssue extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'library';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d', 'returned_on' => 'date:Y-m-d', 'fine_amount' => 'integer'];
    }

    public function activityLabel(): string
    {
        return 'library loan #'.$this->id;
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryCopy::class, 'library_copy_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'borrower_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'borrower_id');
    }

    /**
     * The borrower, whichever kind they are.
     */
    public function borrower(): Student|Employee|null
    {
        return $this->borrower_type === 'student' ? $this->student : $this->employee;
    }
}
