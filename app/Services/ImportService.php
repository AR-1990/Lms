<?php

namespace App\Services;

use App\Http\Requests\Hr\SaveEmployeeRequest;
use App\Http\Requests\Students\SaveStudentRequest;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\FeeHead;
use App\Models\Lookup;
use App\Models\PaymentMethod;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Bringing existing records in from a spreadsheet (CSV): students with their guardians, staff, and opening fee
 * balances. Every row is checked with the same rules as the screens; nothing is saved unless every row is valid.
 */
class ImportService
{
    /**
     * Columns of each template (* = required) and the permission that may import it.
     *
     * @var array<string, array{permission: string, label: string, columns: array<int, string>}>
     */
    public const TYPES = [
        'students' => ['permission' => 'manage-students', 'label' => 'Students & guardians', 'columns' => [
            'admission_no', 'first_name*', 'middle_name', 'last_name', 'gender*', 'date_of_birth*', 'b_form_no', 'admission_date', 'class*', 'section', 'roll_no',
            'religion', 'blood_group', 'phone', 'address', 'city', 'previous_school',
            'guardian_name*', 'guardian_relation', 'guardian_phone*', 'guardian_cnic', 'guardian_email',
            'second_guardian_name', 'second_guardian_relation', 'second_guardian_phone', 'second_guardian_cnic',
        ]],
        'staff' => ['permission' => 'manage-hr', 'label' => 'Staff', 'columns' => [
            'first_name*', 'middle_name', 'last_name', 'father_or_spouse_name', 'gender*', 'date_of_birth', 'cnic', 'phone*', 'email',
            'department', 'designation*', 'employment_type*', 'joining_date*', 'city', 'current_address', 'bank', 'bank_account_title', 'bank_account_no', 'iban',
        ]],
        'opening_balances' => ['permission' => 'manage-fees', 'label' => 'Opening fee balances', 'columns' => [
            'admission_no*', 'amount*', 'as_of_date*', 'note',
        ]],
        'staff_attendance' => ['permission' => 'manage-staff-attendance', 'label' => 'Biometric attendance', 'columns' => [
            'employee_code*', 'date*', 'time*',
        ]],
    ];

    public const MAX_ROWS = 2000;

    public function __construct(
        private StudentRecordService $studentService,
        private EmployeeService $employeeService,
        private FeeInvoiceService $invoiceService,
        private FeePaymentService $paymentService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Header-only CSV the office fills in (starred columns are required).
     */
    public function template(string $type): StreamedResponse
    {
        $columns = self::TYPES[$type]['columns'];

        return response()->streamDownload(function () use ($columns): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        }, Str::slug(self::TYPES[$type]['label']).'-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Check every row; when $save is true and every row is valid, save them all at once.
     *
     * @return array{type: string, rows: int, valid: int, imported: int, errors: array<int, array{row: int, messages: array<int, string>}>}
     */
    public function run(string $type, UploadedFile $file, bool $save, User $user): array
    {
        $rows = $this->read($file);
        $prepared = [];
        $errors = [];

        foreach ($rows as $line => $row) {
            try {
                $prepared[$line] = match ($type) {
                    'students' => $this->student($row),
                    'staff' => $this->staff($row),
                    'opening_balances' => $this->balance($row),
                    'staff_attendance' => $this->punch($row),
                };
            } catch (ValidationException $exception) {
                $errors[] = ['row' => $line, 'messages' => collect($exception->errors())->flatten()->values()->all()];
            }
        }

        $errors = [...$errors, ...$this->duplicates($type, $prepared)];
        $result = ['type' => $type, 'rows' => count($rows), 'valid' => count($rows) - count(array_unique(array_column($errors, 'row'))), 'imported' => 0, 'errors' => $errors];

        if (! $save || $errors !== [] || $prepared === []) {
            return $result;
        }

        // Punches are grouped into one attendance day per employee: first punch in, last punch out.
        if ($type === 'staff_attendance') {
            $days = collect($prepared)->groupBy(fn (array $punch): string => $punch['employee_id'].'|'.$punch['date'])
                ->map(fn ($punches): array => ['employee_id' => $punches->first()['employee_id'], 'date' => $punches->first()['date'], 'times' => $punches->pluck('time')->all()])
                ->values()->all();
            $saved = app(AttendanceService::class)->importPunches($days, $user);
            $result['imported'] = count($prepared);
            $result['days'] = $saved;
            $this->activityLogger->record('imports', 'imported', "{$user->name} imported ".count($prepared).' biometric punch(es): '.$saved['saved'].' attendance day(s), '.$saved['skipped'].' kept as marked by hand, from '.$file->getClientOriginalName());

            return $result;
        }

        DB::transaction(function () use ($type, $prepared, $user): void {
            foreach ($prepared as $data) {
                match ($type) {
                    'students' => $this->studentService->create($data),
                    'staff' => $this->employeeService->create($data),
                    'opening_balances' => $this->saveBalance($data, $user),
                };
            }
        });

        $result['imported'] = count($prepared);
        $this->activityLogger->record('imports', 'imported', "{$user->name} imported ".count($prepared).' '.Str::lower(self::TYPES[$type]['label']).' row(s) from '.$file->getClientOriginalName());

        return $result;
    }

    /**
     * @return array<int, array<string, string>> rows keyed by their line number in the file (header = line 1)
     */
    private function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle) ?: [];
        // Excel adds a byte-order mark; the template's stars only mark required columns.
        $header = array_map(fn ($name): string => Str::snake(trim(str_replace(['*', "\u{FEFF}"], '', (string) $name))), $header);
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                throw ValidationException::withMessages(['file' => ['A file can have at most '.self::MAX_ROWS.' rows. Split it into smaller files.']]);
            }

            $rows[$line] = array_combine($header, array_pad(array_map(fn ($value): string => trim((string) $value), array_slice($values, 0, count($header))), count($header), ''));
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['The file has no rows under the header.']]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function student(array $row): array
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = $this->byName(SchoolClass::class, $row['class'] ?? '', 'class');
        $section = filled($row['section'] ?? null)
            ? (Section::where('academic_session_id', $session->id)->where('class_id', $class)->where('name', $row['section'])->value('id') ?? $this->fail('section', "Section \"{$row['section']}\" does not exist in this class for {$session->name}."))
            : null;

        $guardians = [[
            'name' => $row['guardian_name'] ?? null,
            'relation_id' => $this->lookup('guardian_relation', ($row['guardian_relation'] ?? '') ?: 'Father'),
            'phone' => $row['guardian_phone'] ?? null,
            'cnic' => ($row['guardian_cnic'] ?? '') ?: null,
            'email' => ($row['guardian_email'] ?? '') ?: null,
            'is_primary_contact' => true,
            'is_fee_payer' => true,
        ]];

        if (filled($row['second_guardian_name'] ?? null)) {
            $guardians[] = [
                'name' => $row['second_guardian_name'],
                'relation_id' => $this->lookup('guardian_relation', ($row['second_guardian_relation'] ?? '') ?: 'Mother'),
                'phone' => $row['second_guardian_phone'] ?? null,
                'cnic' => ($row['second_guardian_cnic'] ?? '') ?: null,
            ];
        }

        $data = array_filter([
            'admission_no' => $row['admission_no'] ?? null,
            'first_name' => $row['first_name'] ?? null,
            'middle_name' => $row['middle_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'gender_id' => $this->lookup('gender', $row['gender'] ?? ''),
            'date_of_birth' => $this->date($row['date_of_birth'] ?? '', 'date_of_birth'),
            'b_form_no' => $row['b_form_no'] ?? null,
            'admission_date' => $this->date(($row['admission_date'] ?? '') ?: now()->toDateString(), 'admission_date'),
            'religion_id' => filled($row['religion'] ?? null) ? $this->lookup('religion', $row['religion']) : null,
            'blood_group_id' => filled($row['blood_group'] ?? null) ? $this->lookup('blood_group', $row['blood_group']) : null,
            'phone' => $row['phone'] ?? null,
            'address_line' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'previous_school' => $row['previous_school'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');

        $data['enrollment'] = array_filter(['academic_session_id' => $session->id, 'class_id' => $class, 'section_id' => $section, 'roll_no' => ($row['roll_no'] ?? '') ?: null], fn ($value): bool => $value !== null);
        $data['guardians'] = $guardians;

        return Validator::make($data, (new SaveStudentRequest)->rules())->validate();
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function staff(array $row): array
    {
        $data = array_filter([
            'first_name' => $row['first_name'] ?? null,
            'middle_name' => $row['middle_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'father_or_spouse_name' => $row['father_or_spouse_name'] ?? null,
            'gender_id' => $this->lookup('gender', $row['gender'] ?? ''),
            'date_of_birth' => filled($row['date_of_birth'] ?? null) ? $this->date($row['date_of_birth'], 'date_of_birth') : null,
            'cnic' => $row['cnic'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'department_id' => filled($row['department'] ?? null) ? $this->byName(Department::class, $row['department'], 'department') : null,
            'designation_id' => $this->byName(Designation::class, $row['designation'] ?? '', 'designation'),
            'employment_type_id' => $this->lookup('employment_type', $row['employment_type'] ?? ''),
            'joining_date' => $this->date($row['joining_date'] ?? '', 'joining_date'),
            'city' => $row['city'] ?? null,
            'current_address' => $row['current_address'] ?? null,
            'bank_id' => filled($row['bank'] ?? null) ? $this->lookup('bank', $row['bank']) : null,
            'bank_account_title' => $row['bank_account_title'] ?? null,
            'bank_account_no' => $row['bank_account_no'] ?? null,
            'iban' => $row['iban'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');

        return Validator::make($data, (new SaveEmployeeRequest)->rules())->validate();
    }

    /**
     * @param  array<string, string>  $row
     * @return array{student: Student, amount: int, as_of: string, note: string|null}
     */
    private function balance(array $row): array
    {
        $student = Student::where('admission_no', $row['admission_no'] ?? '')->first() ?? $this->fail('admission_no', 'No student has admission number "'.($row['admission_no'] ?? '').'".');
        $amount = str_replace([',', 'Rs', ' '], '', $row['amount'] ?? '');

        if (! is_numeric($amount) || (float) $amount == 0.0) {
            $this->fail('amount', 'The amount must be a number other than 0 (use a minus sign for money paid in advance).');
        }

        if ($student->currentEnrollment()->doesntExist()) {
            $this->fail('admission_no', "{$student->fullName()} is not enrolled in the current session.");
        }

        return ['student' => $student, 'amount' => (int) round((float) $amount * 100), 'as_of' => $this->date($row['as_of_date'] ?? '', 'as_of_date'), 'note' => ($row['note'] ?? '') ?: null];
    }

    /**
     * One punch from the device export: the employee by code, the day and the time.
     *
     * @param  array<string, string>  $row
     * @return array{employee_id: int, date: string, time: string}
     */
    private function punch(array $row): array
    {
        $employee = Employee::where('employee_code', $row['employee_code'] ?? '')->value('id') ?? $this->fail('employee_code', 'No employee has the code "'.($row['employee_code'] ?? '').'".');
        $time = trim($row['time'] ?? '');

        foreach (['H:i:s', 'H:i', 'h:i A', 'h:i:s A', 'g:i A'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, strtoupper($time));

                if ($parsed !== false) {
                    return ['employee_id' => $employee, 'date' => $this->date($row['date'] ?? '', 'date'), 'time' => $parsed->format('H:i:s')];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $this->fail('time', 'Time "'.$time.'" is not a time (use 08:05 or 8:05 AM).');
    }

    /**
     * Dues become a "Previous Balance" challan; a minus amount becomes an advance that pays later challans.
     *
     * @param  array{student: Student, amount: int, as_of: string, note: string|null}  $data
     */
    private function saveBalance(array $data, User $user): void
    {
        if ($data['amount'] > 0) {
            $this->invoiceService->manual([
                'student_id' => $data['student']->id,
                'issue_date' => $data['as_of'],
                'due_date' => $data['as_of'],
                'notes' => $data['note'] ?? 'Opening balance brought in by import',
                'items' => [['fee_head_id' => FeeHead::where('code', 'OPB')->value('id'), 'amount' => $data['amount'], 'description' => 'Previous balance as of '.Carbon::parse($data['as_of'])->format('d M Y')]],
            ]);

            return;
        }

        $this->paymentService->receive([
            'student_id' => $data['student']->id,
            'payment_method_id' => PaymentMethod::where('code', PaymentMethod::OPENING_ADVANCE)->value('id'),
            'amount' => -$data['amount'],
            'received_on' => $data['as_of'],
            'remarks' => $data['note'] ?? 'Opening advance brought in by import',
        ], true);
    }

    /**
     * The same admission number, B-Form, CNIC or email twice in one file.
     *
     * @param  array<int, array<string, mixed>>  $prepared
     * @return array<int, array{row: int, messages: array<int, string>}>
     */
    private function duplicates(string $type, array $prepared): array
    {
        $keys = ['students' => ['admission_no', 'b_form_no'], 'staff' => ['cnic', 'email'], 'opening_balances' => [], 'staff_attendance' => []][$type];
        $seen = [];
        $errors = [];

        foreach ($prepared as $line => $data) {
            foreach ($keys as $key) {
                $value = $data[$key] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                if (isset($seen[$key][$value])) {
                    $errors[] = ['row' => $line, 'messages' => [Str::headline($key)." {$value} is also on row {$seen[$key][$value]}."]];
                }

                $seen[$key][$value] ??= $line;
            }
        }

        return $errors;
    }

    private function lookup(string $type, string $name): int
    {
        $id = Lookup::where('type', $type)->whereRaw('lower(name) = ?', [Str::lower(trim($name))])->value('id');

        return $id ?? $this->fail($type, Str::headline($type).' "'.$name.'" is not in the list'.($name === '' ? ' (it is required)' : '').'. Use one of: '.Lookup::where('type', $type)->orderBy('sort_order')->pluck('name')->implode(', ').'.');
    }

    /**
     * @param  class-string  $model
     */
    private function byName(string $model, string $name, string $field): int
    {
        $id = $model::whereRaw('lower(name) = ?', [Str::lower(trim($name))])->value('id');

        return $id ?? $this->fail($field, Str::headline($field).' "'.$name.'" was not found'.($name === '' ? ' (it is required)' : '').'.');
    }

    /**
     * Accepts 2026-08-01, 01/08/2026 and 01-08-2026.
     */
    private function date(string $value, string $field): string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($value));

                if ($date !== false && $date->format($format) === trim($value)) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $this->fail($field, Str::headline($field).' "'.$value.'" is not a date (use 2026-08-01 or 01/08/2026).');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
