<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What staff are rated on (Punctuality, Teaching Quality…), its weight in the overall score and who it applies to.
 */
#[Fillable(['name', 'description', 'weight_percent', 'applies_to', 'sort_order', 'is_active'])]
class AppraisalCriterion extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const APPLIES_TO = ['all', 'teaching', 'non_teaching'];

    protected $table = 'appraisal_criteria';

    protected string $activityModule = 'appraisals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weight_percent' => 'float', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
