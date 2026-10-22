<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diary\SaveDiaryEntryRequest;
use App\Http\Resources\DiaryEntryResource;
use App\Models\Diary;
use App\Models\DiaryEntry;
use App\Models\Section;
use App\Models\Student;
use App\Services\DiaryService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiaryController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private DiaryService $diaryService) {}

    /**
     * A section's page for a day, with remarks and who has not signed (teachers).
     */
    public function page(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $section = Section::findOrFail($validated['section_id']);
        $this->diaryService->ensureCanWrite($request->user(), $section);
        ['diary' => $diary, 'students' => $students, 'unsigned' => $unsigned] = $this->diaryService->page($section, $validated['date']);

        return $this->successResponse([
            'id' => $diary?->id,
            'date' => $validated['date'],
            'status' => $diary?->status ?? 'empty',
            'note' => $diary?->class_teacher_note,
            'published_at' => $diary?->published_at?->toIso8601String(),
            'entries' => $diary ? DiaryEntryResource::collection($diary->entries)->resolve() : [],
            'remarks' => $diary ? $diary->remarks->map(fn ($remark): array => ['id' => $remark->id, 'student' => $remark->student?->fullName(), 'type' => $remark->remarkType?->name, 'remark' => $remark->remark])->all() : [],
            'students' => $students,
            'unsigned' => $unsigned,
        ], 'Diary retrieved.');
    }

    public function storeEntry(SaveDiaryEntryRequest $request): JsonResponse
    {
        return $this->successResponse(new DiaryEntryResource($this->diaryService->addEntry($request->validated(), $request->user())), 'Added to the diary.', 201);
    }

    public function updateEntry(SaveDiaryEntryRequest $request, DiaryEntry $diaryEntry): JsonResponse
    {
        return $this->successResponse(new DiaryEntryResource($this->diaryService->updateEntry($diaryEntry, $request->validated(), $request->user())), 'Diary entry updated.');
    }

    public function destroyEntry(DiaryEntry $diaryEntry): JsonResponse
    {
        return $this->deleteWithApproval($diaryEntry, 'Diary entry removed.');
    }

    public function note(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->diaryService->saveNote(Section::findOrFail($validated['section_id']), $validated['date'], $validated['note'] ?? null, $request->user());

        return $this->successResponse(null, 'Note saved.');
    }

    public function publish(Request $request, Diary $diary): JsonResponse
    {
        $diary = $this->diaryService->publish($diary, $request->user());

        return $this->successResponse(['id' => $diary->id, 'status' => $diary->status, 'published_at' => $diary->published_at?->toIso8601String()], 'Diary published to parents.');
    }

    public function storeRemark(Request $request, Diary $diary): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'remark_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'diary_remark_type')->whereNull('deleted_at')],
            'remark' => ['required', 'string', 'max:1000'],
        ]);
        $remark = $this->diaryService->addRemark($diary, $validated, $request->user());

        return $this->successResponse(['id' => $remark->id, 'student' => $remark->student?->fullName(), 'type' => $remark->remarkType?->name, 'remark' => $remark->remark], 'Remark added.', 201);
    }

    /**
     * Parent "signature" on a published page (web or app).
     */
    public function acknowledge(Request $request, Diary $diary): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $device = $request->header('X-Platform');
        $acknowledgement = $this->diaryService->acknowledge($diary, Student::findOrFail($validated['student_id']), $request->user(), $validated['comment'] ?? null, in_array($device, ['web', 'android', 'ios'], true) ? $device : null);

        return $this->successResponse(['acknowledged_at' => $acknowledgement->acknowledged_at->toIso8601String()], 'Diary signed.');
    }

    /**
     * Published diary for one student — staff, the student, or their parent.
     */
    public function student(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(['manage-diary', 'manage-students']) || in_array($student->id, Student::ownIdsFor($user), true), 404);
        $validated = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);

        return $this->successResponse($this->diaryService->forStudent($student, $validated['from'] ?? now()->subDays(14)->toDateString(), $validated['to'] ?? now()->toDateString()), 'Diary retrieved.');
    }
}
