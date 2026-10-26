<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    /**
     * Lookup types whose options are fixed standards and must not be deleted.
     *
     * @var array<int, string>
     */
    private const SYSTEM_TYPES = ['gender', 'blood_group'];

    /**
     * Default master data for every dynamic dropdown in PLAN.md. Admins can add, rename,
     * reorder, or deactivate these from the Lookups screen.
     *
     * @var array<string, array<int, string|array{0: string, 1: string}>>
     */
    private const OPTIONS = [
        'gender' => ['Male', 'Female'],
        'religion' => ['Islam', 'Christianity', 'Hinduism', 'Sikhism', 'Other'],
        'nationality' => ['Pakistani', 'Other'],
        'blood_group' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'marital_status' => ['Single', 'Married', 'Widowed', 'Divorced'],
        'guardian_relation' => ['Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt', 'Grandfather', 'Grandmother', 'Guardian'],
        'occupation' => ['Business', 'Government Service', 'Private Job', 'Doctor', 'Engineer', 'Teacher', 'Armed Forces', 'Self-employed', 'Homemaker', 'Other'],
        'qualification_level' => ['Matric', 'Intermediate', "Bachelor's", "Master's", 'MPhil', 'PhD', 'Diploma'],
        'medical_condition' => ['None', 'Asthma', 'Diabetes', 'Allergy', 'Epilepsy', 'Other'],
        'bank' => ['HBL', 'MCB Bank', 'UBL', 'Allied Bank', 'Bank Alfalah', 'Meezan Bank', 'National Bank of Pakistan', 'Askari Bank', 'Faysal Bank', 'Standard Chartered'],
        'wing' => ['Early Years', 'Junior School', 'Middle School', 'Senior School'],
        'subject_type' => ['Core', 'Elective', 'Co-curricular'],
        'calendar_event_type' => ['Holiday', 'Exam', 'Parent-Teacher Meeting', 'Event', 'Sports'],
        'house' => ['Red House', 'Blue House', 'Green House', 'Yellow House'],
        'discipline_category' => ['Late Arrival', 'Uniform', 'Homework', 'Behaviour', 'Bullying', 'Other'],
        'achievement_category' => ['Academic', 'Sports', 'Arts', 'Debate', 'Science', 'Community Service'],
        'exam_type' => ['Written', 'Oral', 'Practical', 'Class Test', 'Pre-Board'],
        'diary_entry_type' => ['Homework', 'Classwork', 'Test Announcement', 'Reminder', 'Remark'],
        'diary_remark_type' => [['Appreciation', 'positive'], ['Incomplete Homework', 'negative'], ['Behaviour', 'negative'], ['Health', 'neutral']],
        'inquiry_source' => ['Website', 'Walk-in', 'Phone Call', 'Referral', 'Social Media', 'Newspaper'],
        'inquiry_status_reason' => ['Fee Concern', 'Distance', 'Joined Another School', 'No Seat Available', 'Not Interested'],
        'admission_rejection_reason' => ['Did Not Pass Test', 'Age Criteria', 'Incomplete Documents', 'Seats Full'],
        'exit_clearance_area' => ['Accounts', 'Library', 'IT', 'Administration'],
        'follow_up_channel' => ['Phone call', 'WhatsApp', 'School visit', 'SMS', 'Email'],
        'book_category' => ['Fiction', 'Reference', 'Textbook', 'Science', 'History', 'Islamic Studies', 'Urdu Literature', 'Children'],
        'conduct' => ['Excellent', 'Very Good', 'Good', 'Satisfactory'],
        'withdrawal_reason' => ['Relocation', 'Financial', 'Transfer to Another School', 'Graduated', 'Medical', 'Other'],
        'employment_type' => ['Permanent', 'Contract', 'Visiting', 'Intern', 'Probation'],
        'contract_type' => ['Fixed Term', 'Permanent', 'Part Time'],
        'pay_grade' => ['Grade A', 'Grade B', 'Grade C', 'Grade D'],
        'job_source' => ['Website', 'Referral', 'Job Portal', 'Walk-in', 'Newspaper', 'Social Media'],
        'interview_round_type' => ['Screening', 'Subject Test', 'Demo Lecture', 'HR Interview', 'Principal Interview'],
        'loan_type' => ['Salary Advance', 'Personal Loan'],
        'appraisal_rating' => ['Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Needs Improvement', 'Unsatisfactory'],
        'exit_reason' => ['Resignation', 'Termination', 'Retirement', 'Contract End', 'Relocation'],
        'asset_condition' => ['New', 'Good', 'Fair', 'Damaged', 'Unusable'],
        'maintenance_type' => ['Repair', 'Servicing', 'Replacement', 'Inspection'],
        'fee_frequency' => ['One Time', 'Monthly', 'Quarterly', 'Half Yearly', 'Yearly'],
        'news_category' => ['Achievements', 'Sports', 'Academics', 'Campus Life', 'Early Years', 'Admissions', 'Notice'],
        'contact_topic' => ['New Student Admission', 'Fee & Accounts', 'Campus Visit', 'Curriculum & Subjects', 'School Transport'],
        'purchase_category' => ['Stationery', 'IT Equipment', 'Furniture', 'Lab Supplies', 'Cleaning Supplies', 'Maintenance', 'Sports Equipment'],
        'unit_of_measure' => [['Piece', 'pcs'], ['Box', 'box'], ['Packet', 'pkt'], ['Ream', 'ream'], ['Dozen', 'dz'], ['Kilogram', 'kg'], ['Litre', 'L'], ['Set', 'set']],
        'stock_adjustment_reason' => ['Damaged', 'Expired', 'Lost', 'Count Correction', 'Opening Stock'],
        'vehicle_type' => ['Bus', 'Van', 'Coaster', 'Car'],
        'transport_stop_area' => ['Gulberg', 'Model Town', 'Johar Town', 'DHA', 'Cantt', 'Township'],
        'skill_group' => ['Academic', 'Personal', 'Social', 'Physical'],
        'skill_rating' => ['Excellent', 'Good', 'Satisfactory', 'Needs Improvement'],
        'counselling_concern' => ['Academic', 'Behavioural', 'Emotional', 'Family', 'Health'],
        'task_priority' => ['Low', 'Normal', 'High', 'Urgent'],
        'task_category' => ['Administration', 'Academics', 'Maintenance', 'Events', 'Admissions', 'IT'],
        'sticky_note_color' => [['Yellow', '#fff3b0'], ['Pink', '#ffd6e0'], ['Mint', '#d4f5e3'], ['Sky', '#d6ecff'], ['Lavender', '#e7dcff'], ['Peach', '#ffe1c7']],
        'document_type' => ['B-Form', 'CNIC', 'Birth Certificate', 'Previous Result Card', 'School Leaving Certificate', 'Photograph', 'Degree / Certificate', 'Experience Letter', 'CV / Resume', 'Medical Certificate', 'Invoice / Bill', 'Contract'],
        'notice_category' => ['General', 'Holiday', 'Event', 'Exam', 'Fee', 'Emergency'],
        // Dashboard quote of the day: [text, code, author].
        'daily_quote' => [
            ['Education is the most powerful weapon which you can use to change the world.', null, 'Nelson Mandela'],
            ['The beautiful thing about learning is that no one can take it away from you.', null, 'B.B. King'],
            ['Tell me and I forget. Teach me and I remember. Involve me and I learn.', null, 'Benjamin Franklin'],
            ['An investment in knowledge pays the best interest.', null, 'Benjamin Franklin'],
            ['The roots of education are bitter, but the fruit is sweet.', null, 'Aristotle'],
            ['Education is not the filling of a pail, but the lighting of a fire.', null, 'W.B. Yeats'],
            ['Seek knowledge from the cradle to the grave.', null, 'Prophetic tradition'],
        ],
    ];

    /**
     * Run the database seeds. Safe to run again: existing options are updated, not duplicated.
     */
    public function run(): void
    {
        Lookup::withoutEvents(function (): void {
            foreach (self::OPTIONS as $type => $options) {
                foreach (array_values($options) as $position => $option) {
                    [$name, $code, $description] = is_array($option) ? $option + [1 => null, 2 => null] : [$option, null, null];

                    Lookup::withTrashed()->updateOrCreate(
                        ['type' => $type, 'name' => $name],
                        [
                            'code' => $code,
                            // Never wipe a description an admin typed in.
                            ...($description === null ? [] : ['description' => $description]),
                            'sort_order' => ($position + 1) * 10,
                            'is_active' => true,
                            'is_system' => in_array($type, self::SYSTEM_TYPES, true),
                            'deleted_at' => null,
                        ],
                    );
                }
            }
        });
    }
}
