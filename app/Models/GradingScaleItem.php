<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['grading_scale_id', 'grade', 'min_percent', 'max_percent', 'grade_point', 'remark'])]
class GradingScaleItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_percent' => 'float', 'max_percent' => 'float', 'grade_point' => 'float'];
    }
}
