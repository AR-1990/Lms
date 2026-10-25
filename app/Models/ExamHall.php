<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['campus_id', 'name', 'rows', 'columns', 'capacity', 'is_active'])]
class ExamHall extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity' => 'integer'];
    }
}
