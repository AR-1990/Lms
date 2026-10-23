<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A user @mentioned in a task comment.
 */
#[Fillable(['task_comment_id', 'user_id'])]
class TaskCommentMention extends Model {}
