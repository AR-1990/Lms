<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student riding from a stop for a session; the monthly challan charges the stop or route fee while it is active.
 */
#[Fillable(['student_id', 'transport_route_id', 'transport_stop_id', 'academic_session_id', 'from_date', 'to_date', 'status', 'remarks'])]
class StudentTransport extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected $table = 'student_transport';

    protected string $activityModule = 'transport';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'from_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(TransportStop::class, 'transport_stop_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
