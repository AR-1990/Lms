<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A person @mentioned in a group message.
 */
#[Fillable(['message_id', 'user_id'])]
class ChatMention extends Model {}
