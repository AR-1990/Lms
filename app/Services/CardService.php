<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\CardTemplate;
use App\Models\Employee;
use App\Models\IdCard;
use App\Models\PrintLog;
use App\Models\Student;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Card templates, issuing student / employee ID cards, printing them as a PDF (with QR) and the
 * public verification page the QR opens.
 */
class CardService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Templates ---------- */

    /**
     * @param  array{card_type?: string|null}  $filters
     */
    public function templatesQuery(array $filters = []): Builder
    {
        return CardTemplate::query()
            ->withCount('fields')
            ->when($filters['card_type'] ?? null, fn (Builder $query, string $type) => $query->where('card_type', $type));
    }

    /**
     * @return array<string, mixed>
     */
    public function templatesDatatable(array $filters): array
    {
        return DataTables::eloquent($this->templatesQuery($filters))->escapeColumns([])->toArray();
    }

    public function findTemplate(CardTemplate $template): CardTemplate
    {
        return $template->load('fields');
    }

    /**
     * Save a template and replace its placed fields. One default template per card type.
     *
     * @param  array<string, mixed>  $data  template fields + fields[]
     */
    public function saveTemplate(?CardTemplate $template, array $data): CardTemplate
    {
        return DB::transaction(function () use ($template, $data): CardTemplate {
            [, $width, $height] = CardTemplate::SIZES[$data['size_key']];
            $attributes = [...collect($data)->except('fields')->all(), 'width_mm' => $width, 'height_mm' => $height, 'orientation' => $width > $height ? 'landscape' : 'portrait'];

            if ($template === null) {
                $template = CardTemplate::create($attributes);
            } else {
                $template->update($attributes);
            }

            if (array_key_exists('fields', $data)) {
                $template->fields()->delete();

                foreach (array_values($data['fields']) as $position => $field) {
                    $template->fields()->create([...$field, 'sort_order' => $position]);
                }

                $this->activityLogger->record('id_cards', 'layout_changed', (auth()->user()?->name ?? 'System')." changed the layout of card template {$template->name}", $template);
            }

            if ($template->is_default) {
                CardTemplate::where('card_type', $template->card_type)->whereKeyNot($template->id)->update(['is_default' => false]);
            }

            return $this->findTemplate($template);
        });
    }

    /**
     * @throws ValidationException when cards were issued on the template
     */
    public function deleteTemplate(CardTemplate $template): void
    {
        if ($template->is_system) {
            throw ValidationException::withMessages(['template' => ['System templates cannot be deleted; change their design instead.']]);
        }

        if (IdCard::where('card_template_id', $template->id)->exists()) {
            throw ValidationException::withMessages(['template' => ['Cards were issued on this template. Mark it inactive instead.']]);
        }

        DB::transaction(function () use ($template): void {
            $template->fields()->delete();
            $template->delete();
        });
    }

    /* ---------- Issued cards ---------- */

    /**
     * @param  array{holder_type?: string|null, status?: string|null, card_template_id?: int|null}  $filters
     */
    public function cardsQuery(array $filters = []): Builder
    {
        return IdCard::query()
            ->with(['template:id,name,card_type', 'student:id,first_name,last_name,admission_no', 'employee:id,first_name,last_name,employee_code'])
            ->when($filters['holder_type'] ?? null, fn (Builder $query, string $type) => $query->where('holder_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['card_template_id'] ?? null, fn (Builder $query, int $id) => $query->where('card_template_id', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function cardsDatatable(array $filters): array
    {
        return DataTables::eloquent($this->cardsQuery($filters))
            ->escapeColumns([])
            ->addColumn('holder_name', fn (IdCard $card): ?string => $card->holder()?->fullName())
            ->addColumn('holder_code', fn (IdCard $card): ?string => $card->holder_type === 'employee' ? $card->employee?->employee_code : $card->student?->admission_no)
            ->addColumn('template_name', fn (IdCard $card): ?string => $card->template?->name)
            ->editColumn('issued_on', fn (IdCard $card): string => $card->issued_on->toDateString())
            ->editColumn('valid_until', fn (IdCard $card): ?string => $card->valid_until?->toDateString())
            ->filterColumn('card_no', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('card_no', 'like', "%{$keyword}%")
                ->orWhere(fn (Builder $students) => $students->where('holder_type', 'student')->whereHas('student', fn (Builder $student) => $student->whereAny(['first_name', 'last_name', 'admission_no'], 'like', "%{$keyword}%")))
                ->orWhere(fn (Builder $employees) => $employees->where('holder_type', 'employee')->whereHas('employee', fn (Builder $employee) => $employee->whereAny(['first_name', 'last_name', 'employee_code'], 'like', "%{$keyword}%")))))
            ->removeColumn('template', 'student', 'employee', 'qr_token')
            ->toArray();
    }

    /**
     * Issue cards to students (by ids or a whole section) or employees. Holders who already
     * have an active card of this type (this session, for students) are skipped.
     *
     * @param  array{holder_type: string, holder_ids?: array<int, int>, section_id?: int|null, card_template_id?: int|null, valid_until?: string|null}  $data
     * @return array{issued: int, skipped: int}
     *
     * @throws ValidationException when there is no template for the card type
     */
    public function issue(array $data): array
    {
        $type = $data['holder_type'];
        $template = isset($data['card_template_id'])
            ? CardTemplate::findOrFail($data['card_template_id'])
            : CardTemplate::where('card_type', $type)->where('is_active', true)->orderByDesc('is_default')->first();

        if ($template === null) {
            throw ValidationException::withMessages(['card_template_id' => ["Create a {$type} card template first."]]);
        }

        $session = AcademicSession::where('is_current', true)->first();
        $holderIds = $this->holderIds($type, $data, $session);

        return DB::transaction(function () use ($type, $template, $session, $holderIds, $data): array {
            $alreadyCarded = IdCard::where('holder_type', $type)
                ->whereIn('holder_id', $holderIds)
                ->where('status', 'active')
                ->when($type === 'student', fn (Builder $query) => $query->where('academic_session_id', $session?->id))
                ->pluck('holder_id')
                ->all();

            $toIssue = array_values(array_diff($holderIds, $alreadyCarded));

            foreach ($toIssue as $holderId) {
                $this->createCard($type, $holderId, $template, $session, $data['valid_until'] ?? null);
            }

            $this->activityLogger->record('id_cards', 'issued', (auth()->user()?->name ?? 'System').' issued '.count($toIssue)." {$type} ID card(s) on {$template->name}");

            return ['issued' => count($toIssue), 'skipped' => count($alreadyCarded)];
        });
    }

    /**
     * Mark a card lost / revoked / expired; a lost card can be reissued at once as a new card.
     *
     * @throws ValidationException when the card is no longer active
     */
    public function changeStatus(IdCard $card, string $status, ?string $reason, bool $reissue): IdCard
    {
        if ($card->status !== 'active') {
            throw ValidationException::withMessages(['status' => ["This card is already {$card->status}."]]);
        }

        return DB::transaction(function () use ($card, $status, $reason, $reissue): IdCard {
            $card->update(['status' => $reissue ? 'replaced' : $status, 'status_reason' => $reason]);

            if (! $reissue) {
                return $card;
            }

            return $this->createCard($card->holder_type, $card->holder_id, $card->template, $card->academicSession, $card->valid_until?->toDateString(), $card->id);
        });
    }

    /**
     * One PDF page per card side, at the template's size; logs every print.
     *
     * @param  Collection<int, IdCard>  $cards
     */
    public function pdf(Collection $cards): Response
    {
        $cards->load(['template.fields', 'academicSession:id,name']);
        $cards->where('holder_type', 'student')->load(['student.bloodGroup:id,name', 'student.guardians', 'student.enrollments.schoolClass:id,name', 'student.enrollments.section:id,name']);
        $cards->where('holder_type', 'employee')->load(['employee.bloodGroup:id,name', 'employee.designation:id,name', 'employee.department:id,name']);

        $schoolName = SystemSetting::query()->value('school_name');
        $pages = $cards->map(fn (IdCard $card): array => ['template' => $card->template, 'values' => $this->values($card, $schoolName)]);

        DB::transaction(function () use ($cards): void {
            foreach ($cards as $card) {
                PrintLog::create(['document_type' => 'id_card', 'printable_type' => IdCard::class, 'printable_id' => $card->id, 'printed_by' => auth()->id(), 'copies' => 1, 'is_reprint' => $card->print_count > 0, 'created_at' => now()]);
                $card->forceFill(['print_count' => $card->print_count + 1, 'last_printed_at' => now()])->saveQuietly();
            }

            $this->activityLogger->record('id_cards', 'printed', (auth()->user()?->name ?? 'System').' printed '.$cards->count().' ID card(s): '.$cards->pluck('card_no')->implode(', '));
        });

        return $this->renderSheet($pages, 'id-cards.pdf');
    }

    /**
     * Sample data for the designer and the template preview PDF.
     *
     * @return array<string, mixed>
     */
    public function sampleValues(CardTemplate $template): array
    {
        return [
            'school_name' => SystemSetting::query()->value('school_name'),
            'full_name' => $template->card_type === 'employee' ? 'Ayesha Siddiqui' : 'Ali Raza Khan',
            'father_name' => $this->labelled($template->card_type === 'employee' ? 'Father / spouse' : 'Parent', 'Muhammad Raza'),
            'admission_no' => $this->labelled('Adm No', 'PIA-2026-00042'),
            'roll_no' => $this->labelled('Roll No', '17'),
            'class_section' => 'Grade 5 A',
            'employee_code' => $this->labelled('Emp Code', 'EMP-0012'),
            'designation' => 'Senior Teacher',
            'department' => $this->labelled('Dept', 'Academics'),
            'blood_group' => $this->labelled('Blood group', 'B+'),
            'date_of_birth' => '14 Mar 2015',
            'emergency_phone' => $this->labelled('Emergency', '0300 1234567'),
            'address' => 'House 12, Street 4, Model Town, Lahore',
            'valid_until' => $this->labelled('Valid till', now()->addYear()->format('d M Y')),
            'card_no' => 'CARD-000123',
            'qr_code' => $template->show_qr ? $this->qr(route('home')) : null,
            'exam_name' => 'Mid Term Examination — Grade 5',
            'exam_roll_no' => $this->labelled('Roll No', 'E1-017'),
            'instructions' => 'Reach the exam hall 15 minutes early. Bring your own stationery. Mobile phones are not allowed.',
            'date_sheet' => [
                ['subject' => 'English', 'date' => '03 Nov', 'time' => '09:00'],
                ['subject' => 'Mathematics', 'date' => '04 Nov', 'time' => '09:00'],
                ['subject' => 'Science', 'date' => '05 Nov', 'time' => '09:00'],
            ],
        ];
    }

    public function preview(CardTemplate $template): Response
    {
        return $this->renderSheet(collect([['template' => $template->load('fields'), 'values' => $this->sampleValues($template)]]), 'preview.pdf');
    }

    /**
     * Copies of a free design (invitation, event pass, visitor card); every print is logged.
     */
    public function printCustom(CardTemplate $template, int $copies): Response
    {
        $template->load('fields');
        $schoolName = SystemSetting::query()->value('school_name');

        PrintLog::create(['document_type' => 'card_template', 'printable_type' => CardTemplate::class, 'printable_id' => $template->id, 'printed_by' => auth()->id(), 'copies' => $copies, 'is_reprint' => false, 'created_at' => now()]);
        $this->activityLogger->record('id_cards', 'printed', (auth()->user()?->name ?? 'System')." printed {$copies} × {$template->name}", $template);

        return $this->renderSheet(collect(range(1, $copies))->map(fn (int $copy): array => [
            'template' => $template,
            'values' => ['school_name' => $schoolName, 'card_no' => str_pad((string) $copy, 4, '0', STR_PAD_LEFT)],
        ]), Str::slug($template->name).'.pdf');
    }

    /**
     * Lay cards on A4 sheets: front and back side by side (front only when the back is empty),
     * as many as fit per page with a 6 mm gap, so no card is ever split across pages.
     *
     * @param  \Illuminate\Support\Collection<int, array{template: CardTemplate, values: array<string, mixed>}>  $pages
     */
    public function renderSheet(\Illuminate\Support\Collection $pages, string $filename): Response
    {
        $gap = 6;
        $margin = 10;
        $sheets = [];
        $current = [];
        $x = $margin;
        $y = $margin;
        $rowHeight = 0;

        foreach ($pages as $page) {
            $template = $page['template'];
            $sides = $template->fields->contains('side', 'back') || filled($template->back_text) ? ['front', 'back'] : ['front'];
            $unitWidth = count($sides) * $template->width_mm + (count($sides) - 1) * 3;

            if ($x + $unitWidth > 210 - $margin + 0.01) {
                $x = $margin;
                $y += $rowHeight + $gap;
                $rowHeight = 0;
            }

            if ($y + $template->height_mm > 297 - $margin + 0.01) {
                $sheets[] = $current;
                $current = [];
                $x = $margin;
                $y = $margin;
            }

            foreach ($sides as $index => $side) {
                $current[] = ['template' => $template, 'values' => $page['values'], 'side' => $side, 'x' => round($x + $index * ($template->width_mm + 3), 1), 'y' => $y];
            }

            $x += $unitWidth + $gap;
            $rowHeight = max($rowHeight, $template->height_mm);
        }

        $sheets[] = $current;

        return Pdf::loadView('pdf.card-sheet', ['sheets' => $sheets])->setPaper('a4')->stream($filename);
    }

    /**
     * What the public QR page shows: photo, name, card status — nothing private.
     *
     * @return array{card_no: string, status: string, valid_until: string|null, holder_type: string, name: string|null, code: string|null, photo_url: string|null, school_name: string|null}
     */
    public function verify(string $token): array
    {
        $card = IdCard::where('qr_token', $token)->with(['student:id,first_name,last_name,admission_no,photo_path,current_status', 'employee:id,first_name,last_name,employee_code,photo_path,status'])->firstOrFail();
        $holder = $card->holder();
        $expired = $card->valid_until !== null && $card->valid_until->isPast() && ! $card->valid_until->isToday();

        return [
            'card_no' => $card->card_no,
            'status' => $card->status === 'active' && $expired ? 'expired' : $card->status,
            'valid_until' => $card->valid_until?->toDateString(),
            'holder_type' => $card->holder_type,
            'name' => $holder?->fullName(),
            'code' => $card->holder_type === 'employee' ? $holder?->employee_code : $holder?->admission_no,
            'photo_url' => $holder?->photo_path ? Storage::disk('public')->url($holder->photo_path) : null,
            'school_name' => SystemSetting::query()->value('school_name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function holderIds(string $type, array $data, ?AcademicSession $session): array
    {
        if (! empty($data['holder_ids'])) {
            return array_map('intval', $data['holder_ids']);
        }

        if ($type === 'student') {
            return Student::query()
                ->where('current_status', 'active')
                ->whereHas('enrollments', fn (Builder $query) => $query->where('academic_session_id', $session?->id)
                    ->when($data['section_id'] ?? null, fn (Builder $inner, int $sectionId) => $inner->where('section_id', $sectionId)))
                ->pluck('id')
                ->all();
        }

        return Employee::whereIn('status', Employee::CURRENT_STATUSES)->pluck('id')->all();
    }

    private function createCard(string $type, int $holderId, CardTemplate $template, ?AcademicSession $session, ?string $validUntil, ?int $replacementOf = null): IdCard
    {
        return IdCard::create([
            'holder_type' => $type,
            'holder_id' => $holderId,
            'card_template_id' => $template->id,
            'card_no' => $this->numberSequenceService->next('id_card'),
            'qr_token' => Str::random(48),
            'academic_session_id' => $type === 'student' ? $session?->id : null,
            'issued_on' => now()->toDateString(),
            'valid_until' => $validUntil ?? ($type === 'student' ? $session?->ends_on?->toDateString() : now()->addYear()->toDateString()),
            'status' => 'active',
            'replacement_of_id' => $replacementOf,
        ]);
    }

    /**
     * Text / image for every field key the template can place.
     *
     * @return array<string, string|null>
     */
    private function values(IdCard $card, ?string $schoolName): array
    {
        $holder = $card->holder();
        $common = [
            'full_name' => $holder?->fullName(),
            'blood_group' => $this->labelled('Blood group', $holder?->bloodGroup?->name),
            'date_of_birth' => $holder?->date_of_birth?->format('d M Y'),
            'valid_until' => $this->labelled('Valid till', $card->valid_until?->format('d M Y')),
            'card_no' => $card->card_no,
            'school_name' => $schoolName,
            'photo' => $holder?->photo_path && Storage::disk('public')->exists($holder->photo_path) ? Storage::disk('public')->path($holder->photo_path) : null,
            'qr_code' => $card->template->show_qr ? $this->qr(route('cards.verify', $card->qr_token)) : null,
        ];

        if ($holder instanceof Employee) {
            return [...$common,
                'employee_code' => $this->labelled('Emp Code', $holder->employee_code),
                'designation' => $holder->designation?->name,
                'department' => $this->labelled('Dept', $holder->department?->name),
                'father_name' => $this->labelled('Father / spouse', $holder->father_or_spouse_name),
                'emergency_phone' => $this->labelled('Phone', $holder->phone),
                'address' => $holder->current_address,
            ];
        }

        $enrollment = $holder?->enrollments->firstWhere('academic_session_id', $card->academic_session_id);
        $guardian = $holder?->guardians->firstWhere('pivot.is_primary_contact', true) ?? $holder?->guardians->first();

        return [...$common,
            'admission_no' => $this->labelled('Adm No', $holder?->admission_no),
            'roll_no' => $this->labelled('Roll No', $enrollment?->roll_no),
            'class_section' => trim(($enrollment?->schoolClass?->name ?? '').' '.($enrollment?->section?->name ?? '')),
            'father_name' => $this->labelled('Parent', $guardian?->name),
            'emergency_phone' => $this->labelled('Emergency', $guardian?->phone),
            'address' => $holder?->address_line,
        ];
    }

    public function labelled(string $label, ?string $value): ?string
    {
        return filled($value) ? "{$label}: {$value}" : null;
    }

    private function qr(string $url): string
    {
        return (new QRCode(new QROptions(['outputInterface' => QRGdImagePNG::class, 'scale' => 6, 'outputBase64' => true])))->render($url);
    }
}
