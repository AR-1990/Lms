<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The option a student chose for one question (none when skipped), and whether it was right.
 */
#[Fillable(['online_test_attempt_id', 'online_test_question_id', 'online_test_option_id', 'is_correct'])]
class OnlineTestAnswer extends Model
{
    use SoftDeletes, TracksAuthor;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
