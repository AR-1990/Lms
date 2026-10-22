<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mark_id', 'old_theory', 'new_theory', 'old_practical', 'new_practical', 'old_absent', 'new_absent', 'reason', 'changed_by', 'created_at'])]
class MarkChangeLog extends Model
{
    public const UPDATED_AT = null;
}
