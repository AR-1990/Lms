<?php

namespace App\Services;

use App\Models\User;

class AccountsService
{
    /**
     * Get Accounts / Finance portal dashboard data.
     */
    public function getAccountsDashboard(User $user): array
    {
        return [
            'officer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'summary' => [
                'collections_today' => 'Rs. 412,000',
                'pending_challans' => 86,
                'overdue_accounts' => 24,
                'payroll_status' => 'Ready',
            ],
            'recent_payments' => $this->getCollections($user)['payments'],
            'fee_breakdown' => [
                ['category' => 'Tuition', 'collected' => 'Rs. 2.4M', 'pending' => 'Rs. 380K'],
                ['category' => 'Transport', 'collected' => 'Rs. 420K', 'pending' => 'Rs. 65K'],
                ['category' => 'Lab / Activity', 'collected' => 'Rs. 185K', 'pending' => 'Rs. 22K'],
            ],
        ];
    }

    /**
     * Collections register.
     */
    public function getCollections(User $user): array
    {
        return [
            'summary' => [
                'today' => 'Rs. 412,000',
                'week' => 'Rs. 2.18M',
                'month' => 'Rs. 8.64M',
                'cash_share' => '18%',
            ],
            'payments' => [
                [
                    'receipt' => 'RCPT-88214',
                    'student' => 'Ayaan Malik (Grade 8)',
                    'amount' => 'Rs. 18,500',
                    'method' => '1-Link',
                    'time' => '09:42 AM',
                    'cashier' => $user->name,
                ],
                [
                    'receipt' => 'RCPT-88211',
                    'student' => 'Sara Ahmed (Grade 10)',
                    'amount' => 'Rs. 22,000',
                    'method' => 'Bank Transfer',
                    'time' => '09:18 AM',
                    'cashier' => $user->name,
                ],
                [
                    'receipt' => 'RCPT-88205',
                    'student' => 'Omar Farooq (Grade 6)',
                    'amount' => 'Rs. 15,750',
                    'method' => 'Cash',
                    'time' => '08:55 AM',
                    'cashier' => $user->name,
                ],
                [
                    'receipt' => 'RCPT-88198',
                    'student' => 'Noor Fatima (Grade 9)',
                    'amount' => 'Rs. 19,200',
                    'method' => 'Card',
                    'time' => '08:31 AM',
                    'cashier' => $user->name,
                ],
            ],
        ];
    }

    /**
     * Pending and issued fee challans.
     */
    public function getChallans(User $user): array
    {
        return [
            'summary' => [
                'pending' => 86,
                'overdue' => 24,
                'issued_today' => 41,
                'cleared_today' => 37,
            ],
            'challans' => [
                [
                    'number' => 'CH-88421',
                    'student' => 'Hira Malik · Grade 5',
                    'amount' => 'Rs. 28,500',
                    'issued' => '01 Sep 2026',
                    'due' => '15 Sep 2026',
                    'status' => 'Due',
                ],
                [
                    'number' => 'CH-88418',
                    'student' => 'Zain Ali · Grade 7',
                    'amount' => 'Rs. 21,000',
                    'issued' => '01 Sep 2026',
                    'due' => '10 Sep 2026',
                    'status' => 'Overdue',
                ],
                [
                    'number' => 'CH-88405',
                    'student' => 'Sara Ahmed · Grade 10',
                    'amount' => 'Rs. 22,000',
                    'issued' => '01 Sep 2026',
                    'due' => '15 Sep 2026',
                    'status' => 'Paid',
                ],
                [
                    'number' => 'CH-88390',
                    'student' => 'Omar Farooq · Grade 6',
                    'amount' => 'Rs. 15,750',
                    'issued' => '30 Aug 2026',
                    'due' => '12 Sep 2026',
                    'status' => 'Paid',
                ],
            ],
        ];
    }

    /**
     * Staff payroll overview.
     */
    public function getPayroll(User $user): array
    {
        return [
            'summary' => [
                'cycle' => 'September 2026',
                'status' => 'Ready',
                'headcount' => 148,
                'net_payable' => 'Rs. 12.4M',
            ],
            'rows' => [
                [
                    'employee' => 'Prof. Sarah Jenkins',
                    'department' => 'Faculty',
                    'gross' => 'Rs. 185,000',
                    'deductions' => 'Rs. 12,400',
                    'net' => 'Rs. 172,600',
                    'status' => 'Approved',
                ],
                [
                    'employee' => 'Nadia Hussain',
                    'department' => 'Accounts',
                    'gross' => 'Rs. 142,000',
                    'deductions' => 'Rs. 9,800',
                    'net' => 'Rs. 132,200',
                    'status' => 'Approved',
                ],
                [
                    'employee' => 'Ms. Sana Iqbal',
                    'department' => 'Faculty',
                    'gross' => 'Rs. 128,000',
                    'deductions' => 'Rs. 8,100',
                    'net' => 'Rs. 119,900',
                    'status' => 'Pending',
                ],
                [
                    'employee' => 'IT Support Desk',
                    'department' => 'Operations',
                    'gross' => 'Rs. 96,000',
                    'deductions' => 'Rs. 5,400',
                    'net' => 'Rs. 90,600',
                    'status' => 'Approved',
                ],
            ],
        ];
    }
}
