<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: who printed or reprinted a document, and when.
 */
#[Fillable(['document_type', 'printable_type', 'printable_id', 'printed_by', 'copies', 'is_reprint', 'created_at'])]
class PrintLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_reprint' => 'boolean'];
    }
}
