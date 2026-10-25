<?php

namespace App\Services;

use App\Models\DisciplineRecord;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\StudentCertificate;
use App\Models\StudentMedicalRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student's medical record, discipline incidents and achievements, and the numbered certificates
 * (bonafide, character) issued to them.
 */
class StudentCareService
{
    /**
     * Record type in the URL => model.
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'medical' => StudentMedicalRecord::class,
        'discipline' => DisciplineRecord::class,
        'achievements' => StudentAchievement::class,
    ];

    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function records(Student $student, bool $forFamily = false): array
    {
        return [
            'medical' => $forFamily ? [] : StudentMedicalRecord::with('condition:id,name')->where('student_id', $student->id)->latest('checked_on')->latest('id')->get(),
            'discipline' => DisciplineRecord::with(['category:id,name', 'reporter:id,first_name,last_name'])->where('student_id', $student->id)->latest('incident_date')->latest('id')->get(),
            'achievements' => StudentAchievement::with('category:id,name')->where('student_id', $student->id)->latest('achieved_on')->latest('id')->get(),
            'certificates' => $forFamily ? [] : StudentCertificate::with(['conduct:id,name', 'issuer:id,name'])->where('student_id', $student->id)->latest('id')->get(),
        ];
    }

    /**
     * Add or change one record; a new discipline record can tell the parents at once.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(string $type, ?Model $record, Student $student, array $data): Model
    {
        return DB::transaction(function () use ($type, $record, $student, $data): Model {
            $notify = (bool) ($data['notify_parents'] ?? false);
            unset($data['notify_parents']);

            if ($type === 'discipline' && $notify) {
                $data['parent_informed'] = true;
                $data['parent_informed_on'] = now()->toDateString();
            }

            $record ??= new (self::TYPES[$type]);
            $record->fill([...$data, 'student_id' => $student->id])->save();

            if ($type === 'discipline' && $notify) {
                $this->notificationService->notify(
                    $this->notificationService->familyUserIds([$student->id])->all(), 'discipline_recorded',
                    'Note from school: '.$student->fullName(), $record->load('category:id,name')->category?->name.' — '.$data['description'].(filled($data['action_taken'] ?? null) ? ' Action: '.$data['action_taken'] : ''),
                    route('family.child', $student), $record,
                );
            }

            return $record;
        });
    }

    public function delete(Model $record): void
    {
        DB::transaction(fn () => $record->delete());
    }

    /**
     * @param  array{certificate_type: string, issued_on?: string|null, purpose?: string|null, conduct_id?: int|null, remarks?: string|null}  $data
     *
     * @throws ValidationException for a bonafide certificate of a student who is not studying here now
     */
    public function issueCertificate(Student $student, array $data): StudentCertificate
    {
        if ($data['certificate_type'] === 'bonafide' && ($student->current_status !== 'active' || $student->currentEnrollment()->doesntExist())) {
            throw ValidationException::withMessages(['certificate_type' => ['A bonafide certificate is only for a student studying here this session.']]);
        }

        return DB::transaction(fn (): StudentCertificate => StudentCertificate::create([
            ...$data,
            'student_id' => $student->id,
            'certificate_no' => $this->numberSequenceService->next('certificate_no'),
            'issued_on' => $data['issued_on'] ?? now()->toDateString(),
        ]));
    }

    public function certificatePdf(StudentCertificate $certificate, User $user): Response
    {
        $student = Student::with(['currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name', 'currentEnrollment.academicSession:id,name', 'guardians' => fn ($query) => $query->orderByDesc('guardian_student.is_primary_contact')])
            ->findOrFail($certificate->student_id);
        $this->activityLogger->record('students', 'printed', "{$user->name} printed {$certificate->certificate_no} for {$student->fullName()}", $certificate);

        return Pdf::loadView('pdf.student-certificate', [
            'certificate' => $certificate->load('conduct:id,name'),
            'student' => $student,
            'settings' => SystemSetting::query()->first(['school_name', 'school_tagline', 'school_address', 'school_phone']),
            'logo' => app(SystemSettingsService::class)->logoFile(),
        ])->setPaper('a4')->stream($certificate->certificate_no.'.pdf');
    }
}
