<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One emoji per person per message; an empty emoji means the reaction was taken back.
 */
#[Fillable(['message_id', 'user_id', 'emoji'])]
class ChatMessageReaction extends Model {}
