<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One MCQ of an online test, with its marks; exactly one of its options is correct.
 */
#[Fillable(['online_test_id', 'question', 'marks', 'sort_order'])]
class OnlineTestQuestion extends Model
{
    use SoftDeletes, TracksAuthor;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['marks' => 'integer', 'sort_order' => 'integer'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(OnlineTestOption::class)->orderBy('sort_order');
    }
}
