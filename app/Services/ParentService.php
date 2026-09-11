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
            'message' => 'Welcome to LMS',
            'portal' => 'parent',
            'user' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'email' => $parent->email,
            ],
        ];
    }

    /**
     * Detailed children profiles.
     */
    public function getChildrenProfiles(User $parent): array
    {
        return [
            'children' => [],
        ];
    }

    /**
     * Fee ledger for linked children.
     */
    public function getFeeLedger(User $parent): array
    {
        return [
            'summary' => [
                'outstanding' => 'Rs. 0',
                'paid_ytd' => 'Rs. 0',
                'next_due' => 'Not Set',
                'open_challans' => 0,
            ],
            'ledger' => [],
        ];
    }

    /**
     * School notices for parents.
     */
    public function getNotices(User $parent): array
    {
        return [
            'notices' => [],
        ];
    }
}
