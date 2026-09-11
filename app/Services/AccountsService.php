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
            'message' => 'Welcome to LMS',
            'portal' => 'accounts',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
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
                'today' => 'Rs. 0',
                'week' => 'Rs. 0',
                'month' => 'Rs. 0',
                'cash_share' => '0%',
            ],
            'payments' => [],
        ];
    }

    /**
     * Pending and issued fee challans.
     */
    public function getChallans(User $user): array
    {
        return [
            'summary' => [
                'pending' => 0,
                'overdue' => 0,
                'issued_today' => 0,
                'cleared_today' => 0,
            ],
            'challans' => [],
        ];
    }

    /**
     * Staff payroll overview.
     */
    public function getPayroll(User $user): array
    {
        return [
            'summary' => [
                'cycle' => 'Not Set',
                'status' => 'Pending',
                'headcount' => 0,
                'net_payable' => 'Rs. 0',
            ],
            'rows' => [],
        ];
    }
}
