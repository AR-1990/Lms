<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['label', 'value', 'suffix', 'icon', 'sort_order', 'is_active'])]
class WebsiteStat extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'website';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'integer', 'is_active' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return $this->label;
    }
}
