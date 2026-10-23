<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock handed to an employee, student, department or room — or sold to a student on a fee challan.
 */
#[Fillable(['issue_no', 'issued_on', 'location_id', 'recipient_type', 'recipient_id', 'purpose', 'purchase_request_id', 'issued_by', 'received_signature', 'is_sale', 'fee_invoice_id', 'total_cost', 'sale_total', 'status', 'journal_entry_id'])]
class StockIssue extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Who can receive stock: recipient_type => model.
     *
     * @var array<string, class-string<Model>>
     */
    public const RECIPIENTS = ['employee' => Employee::class, 'student' => Student::class, 'department' => Department::class, 'location' => Location::class];

    protected string $activityModule = 'inventory';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'issued_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date', 'received_signature' => 'boolean', 'is_sale' => 'boolean', 'total_cost' => 'integer', 'sale_total' => 'integer'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockIssueItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }

    public function recipientName(): ?string
    {
        return Asset::holderName($this->recipient_type, $this->recipient_id);
    }
}
