<?php

namespace App\Services;

use App\Models\User;

class ParentService
{
    /**
     * Get Parent portal dashboard data.
     */
    public function getParentDashboard(User $parent): array
    {
        return [
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'email' => $parent->email,
            ],
            'children' => $this->getChildrenProfiles($parent)['children'],
            'summary' => [
                'outstanding_fees' => 'Rs. 28,500',
                'next_ptm' => '05 Oct 2026',
                'unread_notices' => 3,
                'today_attendance' => 'Both Present',
            ],
            'recent_notices' => array_slice($this->getNotices($parent)['notices'], 0, 3),
        ];
    }

    /**
     * Detailed children profiles.
     */
    public function getChildrenProfiles(User $parent): array
    {
        return [
            'children' => [
                [
                    'name' => 'Ayaan Malik',
                    'roll_no' => 'PIA-2026-4412',
                    'grade' => 'Grade 8 — Section B',
                    'homeroom' => 'Ms. Sana Iqbal',
                    'attendance' => '97.1%',
                    'fee_status' => 'Paid',
                    'cgpa' => '3.82',
                ],
                [
                    'name' => 'Hira Malik',
                    'roll_no' => 'PIA-2026-5521',
                    'grade' => 'Grade 5 — Section A',
                    'homeroom' => 'Mr. Bilal Ahmed',
                    'attendance' => '94.8%',
                    'fee_status' => 'Due',
                    'cgpa' => '3.65',
                ],
            ],
        ];
    }

    /**
     * Fee ledger for linked children.
     */
    public function getFeeLedger(User $parent): array
    {
        return [
            'summary' => [
                'outstanding' => 'Rs. 28,500',
                'paid_ytd' => 'Rs. 186,000',
                'next_due' => '15 Sep 2026',
                'open_challans' => 1,
            ],
            'ledger' => [
                [
                    'challan' => 'CH-88421',
                    'student' => 'Hira Malik',
                    'description' => 'September Tuition + Lab',
                    'amount' => 'Rs. 28,500',
                    'due' => '15 Sep 2026',
                    'status' => 'Due',
                ],
                [
                    'challan' => 'CH-87102',
                    'student' => 'Ayaan Malik',
                    'description' => 'August Tuition',
                    'amount' => 'Rs. 18,500',
                    'due' => '15 Aug 2026',
                    'status' => 'Paid',
                ],
                [
                    'challan' => 'CH-87098',
                    'student' => 'Hira Malik',
                    'description' => 'August Tuition',
                    'amount' => 'Rs. 16,750',
                    'due' => '15 Aug 2026',
                    'status' => 'Paid',
                ],
            ],
        ];
    }

    /**
     * School notices for parents.
     */
    public function getNotices(User $parent): array
    {
        return [
            'notices' => [
                [
                    'title' => 'Mid-Term Fee Challan Generated',
                    'date' => '01 Sep 2026',
                    'type' => 'Finance',
                    'priority' => 'High',
                    'body' => 'September challans are available in the Fees section. Please clear dues before the due date.',
                ],
                [
                    'title' => 'Parent-Teacher Meeting Reminder',
                    'date' => '28 Aug 2026',
                    'type' => 'Academics',
                    'priority' => 'Normal',
                    'body' => 'PTM for Grades 5–8 is scheduled for 05 Oct 2026 in the Main Auditorium.',
                ],
                [
                    'title' => 'Sports Gala Consent Form',
                    'date' => '22 Aug 2026',
                    'type' => 'Events',
                    'priority' => 'Normal',
                    'body' => 'Consent forms for the Inter-School Sports Gala must be submitted by 12 Sep 2026.',
                ],
                [
                    'title' => 'Library Book Return Drive',
                    'date' => '18 Aug 2026',
                    'type' => 'Campus',
                    'priority' => 'Low',
                    'body' => 'Students with overdue library books should return them before mid-term assessments.',
                ],
            ],
        ];
    }
}
