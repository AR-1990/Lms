<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['activity_log_id', 'field', 'old_value', 'new_value'])]
class ActivityLogChange extends Model
{
    public $timestamps = false;
}
