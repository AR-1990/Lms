<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Account;
use App\Models\AdmissionApplication;
use App\Models\AdmissionInquiry;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\AppraisalCriterion;
use App\Models\AppraisalCycle;
use App\Models\ApprovalRequest;
use App\Models\AssessmentSkill;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\CalendarEvent;
use App\Models\Campus;
use App\Models\CardTemplate;
use App\Models\ClassSubject;
use App\Models\ConcessionType;
use App\Models\CounsellingNote;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DiaryEntry;
use App\Models\DisciplineRecord;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\ExamTerm;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FiscalYear;
use App\Models\GradingScale;
use App\Models\Homework;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\JournalEntry;
use App\Models\LateFeeRule;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\Lookup;
use App\Models\MessageTemplate;
use App\Models\OnlineTest;
use App\Models\PaymentMethod;
use App\Models\PayrollAdjustment;
use App\Models\PerformanceAlertRule;
use App\Models\Period;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\StudentConcession;
use App\Models\StudentGoal;
use App\Models\StudentMedicalRecord;
use App\Models\Subject;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaxSlab;
use App\Models\TeacherAssignment;
use App\Models\TimetableSlot;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\VoucherType;
use App\Models\WebsiteFaq;
use App\Models\WebsitePost;
use App\Models\WebsiteSectionItem;
use App\Models\WebsiteStat;
use App\Models\WebsiteTestimonial;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

/**
 * Admin approval for deletions and purchases. A user with `approve-requests` deletes at once
 * (recorded as approved by themselves); anyone else creates a pending request that an
 * administrator approves (the record is then soft deleted) or rejects. Every step is logged.
 */
class ApprovalService
{
    public const APPROVER_PERMISSION = 'approve-requests';

    /**
     * The Service method that deletes each kind of record, with its "still in use" checks.
     *
     * @var array<class-string<Model>, array{0: class-string, 1: string}>
     */
    private const DELETERS = [
        AcademicSession::class => [AcademicSessionService::class, 'delete'],
        Announcement::class => [AnnouncementService::class, 'delete'],
        AppraisalCycle::class => [AppraisalService::class, 'deleteCycle'],
        AppraisalCriterion::class => [AppraisalService::class, 'deleteCriterion'],
        Asset::class => [AssetService::class, 'delete'],
        CounsellingNote::class => [CounsellingService::class, 'delete'],
        WorkShift::class => [WorkShiftService::class, 'delete'],
        DisciplineRecord::class => [StudentCareService::class, 'delete'],
        OnlineTest::class => [OnlineTestService::class, 'delete'],
        StudentMedicalRecord::class => [StudentCareService::class, 'delete'],
        StudentAchievement::class => [StudentCareService::class, 'delete'],
        PayrollAdjustment::class => [PayrollService::class, 'deleteAdjustment'],
        LateFeeRule::class => [FeeAutomationService::class, 'deleteRule'],
        TransportRoute::class => [TransportService::class, 'deleteRoute'],
        Vehicle::class => [TransportService::class, 'deleteVehicle'],
        Expense::class => [ExpenseService::class, 'delete'],
        ExpenseCategory::class => [ExpenseService::class, 'deleteCategory'],
        InventoryItem::class => [StockService::class, 'deleteItem'],
        ItemCategory::class => [StockService::class, 'deleteCategory'],
        PurchaseOrder::class => [ProcurementService::class, 'deleteOrder'],
        AssetCategory::class => [AssetService::class, 'deleteCategory'],
        Location::class => [AssetService::class, 'deleteLocation'],
        AnnouncementCategory::class => [AnnouncementService::class, 'deleteCategory'],
        Account::class => [AccountService::class, 'delete'],
        AdmissionApplication::class => [AdmissionService::class, 'deleteApplication'],
        AdmissionInquiry::class => [AdmissionService::class, 'deleteInquiry'],
        AssessmentSkill::class => [ExamSetupService::class, 'delete'],
        Attachment::class => [AttachmentService::class, 'delete'],
        BankAccount::class => [BankAccountService::class, 'delete'],
        CalendarEvent::class => [CalendarService::class, 'delete'],
        Campus::class => [CampusService::class, 'delete'],
        CardTemplate::class => [CardService::class, 'deleteTemplate'],
        ClassSubject::class => [CurriculumService::class, 'delete'],
        ConcessionType::class => [FeeSetupService::class, 'deleteConcessionType'],
        Department::class => [DepartmentService::class, 'delete'],
        Designation::class => [DesignationService::class, 'delete'],
        DiaryEntry::class => [DiaryService::class, 'deleteEntry'],
        Employee::class => [EmployeeService::class, 'delete'],
        Exam::class => [ExamService::class, 'delete'],
        ExamHall::class => [ExamSetupService::class, 'delete'],
        ExamTerm::class => [ExamSetupService::class, 'delete'],
        FeeHead::class => [FeeSetupService::class, 'deleteHead'],
        FeeStructure::class => [FeeSetupService::class, 'deleteStructure'],
        FiscalYear::class => [FiscalYearService::class, 'delete'],
        GradingScale::class => [ExamSetupService::class, 'delete'],
        Homework::class => [HomeworkService::class, 'delete'],
        JobApplication::class => [RecruitmentService::class, 'deleteApplication'],
        JobOpening::class => [RecruitmentService::class, 'deleteOpening'],
        JournalEntry::class => [JournalService::class, 'deleteDraft'],
        Lookup::class => [LookupService::class, 'delete'],
        MessageTemplate::class => [NotificationService::class, 'deleteTemplate'],
        PaymentMethod::class => [PaymentMethodService::class, 'delete'],
        PerformanceAlertRule::class => [ExamSetupService::class, 'delete'],
        Period::class => [PeriodService::class, 'delete'],
        PurchaseRequest::class => [PurchaseRequestService::class, 'delete'],
        Role::class => [RolePermissionService::class, 'deleteRole'],
        SchoolClass::class => [SchoolClassService::class, 'delete'],
        Section::class => [SectionService::class, 'delete'],
        Student::class => [StudentRecordService::class, 'delete'],
        StudentConcession::class => [FeeSetupService::class, 'deleteConcession'],
        StudentGoal::class => [PerformanceService::class, 'deleteGoal'],
        Subject::class => [SubjectService::class, 'delete'],
        TeacherAssignment::class => [TeacherAssignmentService::class, 'delete'],
        TimetableSlot::class => [TimetableService::class, 'delete'],
        User::class => [UserService::class, 'deleteUser'],
        Vendor::class => [VendorService::class, 'delete'],
        VoucherType::class => [VoucherTypeService::class, 'delete'],
        WebsiteFaq::class => [WebsiteContentService::class, 'delete'],
        WebsitePost::class => [WebsiteContentService::class, 'delete'],
        WebsiteStat::class => [WebsiteContentService::class, 'delete'],
        WebsiteTestimonial::class => [WebsiteContentService::class, 'delete'],
        Task::class => [TaskService::class, 'delete'],
        LeaveType::class => [LeaveService::class, 'deleteType'],
        SalaryComponent::class => [PayrollService::class, 'deleteComponent'],
        TaxSlab::class => [PayrollService::class, 'deleteTaxSlab'],
        WebsiteSectionItem::class => [WebsitePageService::class, 'deleteItem'],
        TaskBoard::class => [TaskService::class, 'deleteBoard'],
    ];

    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * Requesters see their own requests; approvers see everyone's.
     *
     * @param  array{status?: string|null, request_type?: string|null, module?: string|null}  $filters
     */
    public function query(User $user, array $filters = []): Builder
    {
        return ApprovalRequest::query()
            ->with(['requester:id,name', 'decider:id,name'])
            ->when(! $user->hasPermission(self::APPROVER_PERMISSION), fn (Builder $query) => $query->where('requested_by', $user->id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['request_type'] ?? null, fn (Builder $query, string $type) => $query->where('request_type', $type))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('module', $module));
    }

    /**
     * @param  array{status?: string|null, request_type?: string|null, module?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(User $user, array $filters): array
    {
        return DataTables::eloquent($this->query($user, $filters))
            ->escapeColumns([])
            ->addColumn('requested_by_name', fn (ApprovalRequest $approval): ?string => $approval->requester?->name)
            ->addColumn('decided_by_name', fn (ApprovalRequest $approval): ?string => $approval->decider?->name)
            ->addColumn('record_type', fn (ApprovalRequest $approval): string => Str::headline(class_basename($approval->subject_type)))
            ->editColumn('requested_at', fn (ApprovalRequest $approval): ?string => $approval->requested_at?->toIso8601String())
            ->editColumn('decided_at', fn (ApprovalRequest $approval): ?string => $approval->decided_at?->toIso8601String())
            ->filterColumn('subject_label', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('subject_label', 'like', "%{$keyword}%")
                ->orWhere('reason', 'like', "%{$keyword}%")))
            ->removeColumn('requester', 'decider')
            ->toArray();
    }

    public function pendingCount(): int
    {
        return ApprovalRequest::where('status', 'pending')->count();
    }

    /**
     * Delete a record, or send the deletion for approval when the user is not an approver.
     *
     * @throws ValidationException when the record cannot be deleted, is already waiting, or no reason is given
     */
    public function requestDelete(Model $record, ?string $reason, User $user): ApprovalRequest
    {
        $this->ensureNotWaiting($record);
        $approver = $user->hasPermission(self::APPROVER_PERMISSION);

        if (! $approver) {
            if (blank($reason)) {
                throw ValidationException::withMessages(['reason' => ['Give a reason; an administrator approves every deletion.']]);
            }

            // Run the delete and roll it back, so "still in use" problems show now, not at approval time.
            $this->dryRun(fn () => $this->runDelete($record->fresh()));
        }

        return DB::transaction(function () use ($record, $reason, $user, $approver): ApprovalRequest {
            $approval = $this->open('delete', $record, $reason, $user, null);

            if ($approver) {
                $this->runDelete($record);
                $this->close($approval, 'approved', $user, null);
                $this->activityLogger->record($approval->module, 'delete_approved', "{$user->name} deleted {$this->describe($approval)} (own approval).".$this->reasonText($approval), $record);
            } else {
                $this->activityLogger->record($approval->module, 'delete_requested', "{$user->name} asked to delete {$this->describe($approval)}.".$this->reasonText($approval), $record);
            }

            return $approval;
        });
    }

    /**
     * Send a purchase request for approval (approvers approve their own at once).
     */
    public function requestPurchase(PurchaseRequest $purchase, User $user): ApprovalRequest
    {
        $approval = $this->open('purchase', $purchase, $purchase->purpose, $user, $purchase->estimated_total);
        $purchase->update(['approval_request_id' => $approval->id]);
        $this->activityLogger->record('purchase_requests', 'purchase_requested', "{$user->name} asked to purchase {$purchase->request_no} (Rs ".number_format($purchase->estimated_total / 100, 2).').', $purchase);

        if ($user->hasPermission(self::APPROVER_PERMISSION)) {
            $this->decide($approval, true, 'Raised by an approver.', $user);
        }

        return $approval->refresh();
    }

    /**
     * Approve (the deletion runs / the purchase is cleared) or reject a pending request.
     *
     * @throws ValidationException when it is no longer pending, the record is gone, or a rejection has no note
     */
    public function decide(ApprovalRequest $approval, bool $approve, ?string $note, User $user): ApprovalRequest
    {
        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ["This request was already {$approval->status}."]]);
        }

        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['decision_note' => ['Say why the request is rejected.']]);
        }

        return DB::transaction(function () use ($approval, $approve, $note, $user): ApprovalRequest {
            $record = $approval->subject_type::find($approval->subject_id);

            if ($record === null) {
                throw ValidationException::withMessages(['status' => ['The record no longer exists.']]);
            }

            if ($approve && $approval->request_type === 'delete') {
                $this->runDelete($record);
            }

            if ($approval->request_type === 'purchase') {
                $record->update(['status' => $approve ? 'approved' : 'rejected']);
            }

            $this->close($approval, $approve ? 'approved' : 'rejected', $user, $note);

            $verb = $approve ? 'approved' : 'rejected';
            $what = $approval->request_type === 'delete' ? 'deletion of '.$this->describe($approval) : 'purchase '.$approval->subject_label;
            $this->activityLogger->record(
                $approval->module,
                "{$approval->request_type}_{$verb}",
                "{$user->name} {$verb} {$what}, requested by ".($approval->requester?->name ?? 'unknown').'.'.$this->reasonText($approval).($note ? " Note: {$note}" : ''),
                $record,
            );

            return $approval->load(['requester:id,name', 'decider:id,name']);
        });
    }

    private function open(string $type, Model $record, ?string $reason, User $user, ?int $amount): ApprovalRequest
    {
        return ApprovalRequest::create([
            'campus_id' => $record->getAttribute('campus_id'),
            'request_type' => $type,
            'module' => method_exists($record, 'activityModule') ? $record->activityModule() : Str::snake(Str::pluralStudly(class_basename($record))),
            'subject_type' => $record::class,
            'subject_id' => $record->getKey(),
            'subject_label' => Str::limit(method_exists($record, 'activityLabel') ? $record->activityLabel() : '#'.$record->getKey(), 250),
            'reason' => $reason,
            'amount' => $amount,
            'status' => 'pending',
            'requested_by' => $user->id,
            'requested_at' => now(),
        ])->setRelation('requester', $user);
    }

    private function close(ApprovalRequest $approval, string $status, User $user, ?string $note): void
    {
        $approval->update(['status' => $status, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);
        $approval->setRelation('decider', $user);
    }

    private function ensureNotWaiting(Model $record): void
    {
        $waiting = ApprovalRequest::where('subject_type', $record::class)->where('subject_id', $record->getKey())->where('status', 'pending')->exists();

        if ($waiting) {
            throw ValidationException::withMessages(['reason' => ['A deletion request for this record is already waiting for approval.']]);
        }
    }

    private function runDelete(Model $record): void
    {
        [$service, $method] = self::DELETERS[$record::class] ?? throw new LogicException('No delete action is registered for '.$record::class.'.');

        app($service)->{$method}($record);
    }

    private function dryRun(callable $action): void
    {
        DB::beginTransaction();

        try {
            $action();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        DB::rollBack();
    }

    private function describe(ApprovalRequest $approval): string
    {
        return Str::headline(class_basename($approval->subject_type)).' "'.$approval->subject_label.'"';
    }

    private function reasonText(ApprovalRequest $approval): string
    {
        return $approval->reason ? " Reason: {$approval->reason}" : '';
    }
}
