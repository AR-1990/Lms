<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One physical book: available, issued, lost, damaged or withdrawn.
 */
#[Fillable(['library_book_id', 'accession_no', 'status', 'remarks'])]
class LibraryCopy extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['available', 'issued', 'lost', 'damaged', 'withdrawn'];

    protected string $activityModule = 'library';

    public function activityLabel(): string
    {
        return 'copy '.$this->accession_no;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }
}
