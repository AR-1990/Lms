<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['asset_audit_id', 'asset_id', 'found', 'condition_id', 'remarks'])]
class AssetAuditItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['found' => 'boolean'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(AssetAudit::class, 'asset_audit_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'condition_id');
    }
}
