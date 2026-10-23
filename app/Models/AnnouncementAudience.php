<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One target of an announcement: `everyone`, `all_students`, `all_parents`, `all_employees`, or a role /
 * class / section / department / user by id.
 */
#[Fillable(['announcement_id', 'audience_type', 'audience_id'])]
class AnnouncementAudience extends Model
{
    use SoftDeletes;
}
