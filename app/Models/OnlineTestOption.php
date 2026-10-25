<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One answer choice of an online test question.
 */
#[Fillable(['online_test_question_id', 'option_text', 'is_correct', 'sort_order'])]
class OnlineTestOption extends Model
{
    use SoftDeletes, TracksAuthor;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'sort_order' => 'integer'];
    }
}
