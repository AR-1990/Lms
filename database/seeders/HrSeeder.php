<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\NumberSequence;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    /**
     * [name, code].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DEPARTMENTS = [
        ['Administration', 'ADM'],
        ['Academics', 'ACD'],
        ['Accounts & Finance', 'FIN'],
        ['Support Services', 'SUP'],
    ];

    /**
     * [name, department code, is teaching].
     *
     * @var array<int, array{0: string, 1: string, 2: bool}>
     */
    private const DESIGNATIONS = [
        ['Principal', 'ADM', false],
        ['Vice Principal', 'ADM', false],
        ['Section Head', 'ACD', true],
        ['Senior Teacher', 'ACD', true],
        ['Teacher', 'ACD', true],
        ['Assistant Teacher', 'ACD', true],
        ['Accountant', 'FIN', false],
        ['Admin Officer', 'ADM', false],
        ['Librarian', 'ACD', false],
        ['Lab Assistant', 'ACD', false],
        ['Office Assistant', 'SUP', false],
        ['Security Guard', 'SUP', false],
    ];

    /**
     * Master HR setup, employee records for the seeded staff logins, and a few demo teachers.
     */
    public function run(): void
    {
        $departments = collect(self::DEPARTMENTS)->mapWithKeys(fn (array $department): array => [
            $department[1] => Department::firstOrCreate(['code' => $department[1]], ['name' => $department[0], 'is_active' => true]),
        ]);

        $designations = collect(self::DESIGNATIONS)->mapWithKeys(fn (array $designation): array => [
            $designation[0] => Designation::firstOrCreate(['name' => $designation[0]], [
                'department_id' => $departments[$designation[1]]->id,
                'is_teaching' => $designation[2],
                'is_active' => true,
            ]),
        ]);

        $permanent = Lookup::where('type', 'employment_type')->orderBy('sort_order')->value('id');
        $genders = Lookup::where('type', 'gender')->pluck('id', 'name');

        $staff = [
            ['teacher@lms.test', 'Senior Teacher', 'Female', true],
            ['accounts@lms.test', 'Accountant', 'Female', false],
        ];

        foreach ($staff as [$email, $designation, $gender, $isTeaching]) {
            $user = User::where('email', $email)->first();

            if ($user === null || Employee::where('user_id', $user->id)->exists()) {
                continue;
            }

            [$firstName, $lastName] = array_pad(explode(' ', preg_replace('/^Prof\.\s*/', '', $user->name), 2), 2, null);

            Employee::create([
                'user_id' => $user->id,
                'employee_code' => $this->nextCode(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender_id' => $genders[$gender] ?? $genders->first(),
                'phone' => '0300'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
                'email' => $user->email,
                'department_id' => $designations[$designation]->department_id,
                'designation_id' => $designations[$designation]->id,
                'employment_type_id' => $permanent,
                'joining_date' => '2024-08-01',
                'is_teaching_staff' => $isTeaching,
                'status' => 'active',
            ]);
        }

        if (Employee::where('is_teaching_staff', true)->count() < 4) {
            foreach (range(1, 4) as $index) {
                Employee::factory()->create([
                    'employee_code' => $this->nextCode(),
                    'gender_id' => $genders->values()[$index % max($genders->count(), 1)] ?? $genders->first(),
                    'department_id' => $departments['ACD']->id,
                    'designation_id' => $designations['Teacher']->id,
                    'employment_type_id' => $permanent,
                    'is_teaching_staff' => true,
                ]);
            }
        }
    }

    /**
     * Uses the same counter as the app so seeded and real codes never clash.
     */
    private function nextCode(): string
    {
        $sequence = NumberSequence::where('type', 'employee_code')->first();
        $number = $sequence->next_number;
        $sequence->forceFill(['next_number' => $number + 1])->saveQuietly();

        return $sequence->format($number);
    }
}
