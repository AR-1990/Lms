<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::upsert([
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'group' => 'users', 'description' => 'Create and maintain user accounts.'],
            ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'group' => 'roles', 'description' => 'Create roles and assign permissions.'],
            ['name' => 'View System Stats', 'slug' => 'view-stats', 'group' => 'admin', 'description' => 'Review system-wide dashboard statistics.'],
            ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'group' => 'admin', 'description' => 'Configure school-wide system settings.'],
            ['name' => 'Manage Classes', 'slug' => 'manage-classes', 'group' => 'academics', 'description' => 'Maintain teacher class operations.'],
            ['name' => 'Record Attendance', 'slug' => 'record-attendance', 'group' => 'academics', 'description' => 'Record daily attendance.'],
            ['name' => 'Submit Grades', 'slug' => 'submit-grades', 'group' => 'academics', 'description' => 'Submit course grades.'],
            ['name' => 'View Enrolled Courses', 'slug' => 'view-courses', 'group' => 'student', 'description' => 'View enrolled course list.'],
            ['name' => 'View Attendance', 'slug' => 'view-attendance', 'group' => 'student', 'description' => 'View attendance records.'],
            ['name' => 'View Grades', 'slug' => 'view-grades', 'group' => 'student', 'description' => 'View academic grades.'],
            ['name' => 'View Children', 'slug' => 'view-children', 'group' => 'parent', 'description' => 'View linked child profiles.'],
            ['name' => 'View Fee Status', 'slug' => 'view-fees', 'group' => 'parent', 'description' => 'View fee status and challans.'],
            ['name' => 'View Notices', 'slug' => 'view-notices', 'group' => 'parent', 'description' => 'View school notices.'],
            ['name' => 'Manage Fee Collections', 'slug' => 'manage-fees', 'group' => 'accounts', 'description' => 'Manage fee collection workflows.'],
            ['name' => 'Issue Fee Refunds', 'slug' => 'issue-refunds', 'group' => 'accounts', 'description' => 'Pay back a leaving student\'s security deposit or unused advance, and reverse refunds.'],
            ['name' => 'View Payroll', 'slug' => 'view-payroll', 'group' => 'accounts', 'description' => 'View payroll information.'],
            ['name' => 'Manage Lookups', 'slug' => 'manage-lookups', 'group' => 'admin', 'description' => 'Maintain dropdown master data (lookups).'],
            ['name' => 'View Activity Log', 'slug' => 'view-activity-log', 'group' => 'admin', 'description' => 'See who did what, on which module.'],
            ['name' => 'Manage Campuses', 'slug' => 'manage-campuses', 'group' => 'admin', 'description' => 'Create and maintain campuses / branches.'],
            ['name' => 'Manage Numbering', 'slug' => 'manage-numbering', 'group' => 'admin', 'description' => 'Configure document number formats (admission no, receipts…).'],
            ['name' => 'Manage Academics', 'slug' => 'manage-academics', 'group' => 'academics', 'description' => 'Sessions, classes, sections, subjects, curriculum and bell schedule.'],
            ['name' => 'Manage Chart of Accounts', 'slug' => 'manage-accounts', 'group' => 'accounts', 'description' => 'Accounts, fiscal years, voucher types, payment methods, bank accounts and vendors.'],
            ['name' => 'Manage Vouchers', 'slug' => 'manage-vouchers', 'group' => 'accounts', 'description' => 'Create and edit draft journal vouchers.'],
            ['name' => 'Post Vouchers', 'slug' => 'post-vouchers', 'group' => 'accounts', 'description' => 'Post vouchers to the ledger and reverse posted vouchers.'],
            ['name' => 'View Ledger', 'slug' => 'view-ledger', 'group' => 'accounts', 'description' => 'View vouchers, account ledgers and the trial balance.'],
            ['name' => 'Manage HR', 'slug' => 'manage-hr', 'group' => 'hr', 'description' => 'Departments, designations, employees and teacher assignments.'],
            ['name' => 'Manage Recruitment', 'slug' => 'manage-recruitment', 'group' => 'hr', 'description' => 'Job openings, candidates, interviews and hiring.'],
            ['name' => 'Manage Admissions', 'slug' => 'manage-admissions', 'group' => 'admissions', 'description' => 'Inquiries, applications, tests, interviews and enrolment.'],
            ['name' => 'Manage Students', 'slug' => 'manage-students', 'group' => 'students', 'description' => 'Student records, guardians, sections and status changes.'],
            ['name' => 'Approve Requests', 'slug' => 'approve-requests', 'group' => 'administration', 'description' => 'Approve or reject deletions and purchase requests. Deletions by these users need no approval.'],
            ['name' => 'Manage Purchase Requests', 'slug' => 'manage-purchases', 'group' => 'hr', 'description' => 'Raise purchase requests that go to an administrator for approval.'],
            ['name' => 'View Performance', 'slug' => 'view-performance', 'group' => 'academics', 'description' => 'Student performance tracker: snapshots, skills, goals and at-risk alerts.'],
            ['name' => 'Manage Exams', 'slug' => 'manage-exams', 'group' => 'academics', 'description' => 'Exam setup, date sheets, admit cards, seating, approving marks and publishing results.'],
            ['name' => 'Manage Diary & Homework', 'slug' => 'manage-diary', 'group' => 'academics', 'description' => 'Write the daily diary and homework for sections the teacher teaches, grade submissions.'],
            ['name' => 'Manage Timetable', 'slug' => 'manage-timetable', 'group' => 'academics', 'description' => 'Weekly class timetables (teacher clash checks).'],
            ['name' => 'Manage Calendar', 'slug' => 'manage-calendar', 'group' => 'academics', 'description' => 'Holidays, exams, meetings and public events.'],
            ['name' => 'Manage Staff Attendance', 'slug' => 'manage-staff-attendance', 'group' => 'hr', 'description' => 'Mark daily staff attendance.'],
            ['name' => 'Manage ID Cards', 'slug' => 'manage-cards', 'group' => 'students', 'description' => 'Card templates, issuing and printing student and employee ID cards.'],
            ['name' => 'Manage Website', 'slug' => 'manage-website', 'group' => 'administration', 'description' => 'Public website stats, testimonials, news & notices and FAQs.'],
            ['name' => 'Manage Announcements', 'slug' => 'manage-announcements', 'group' => 'communication', 'description' => 'Write, schedule and publish announcements; manage their categories, message templates and the delivery log.'],
            ['name' => 'Manage Leave', 'slug' => 'manage-leave', 'group' => 'hr', 'description' => 'Leave types, approving staff leave and adjusting balances.'],
            ['name' => 'Manage Payroll', 'slug' => 'manage-payroll', 'group' => 'hr', 'description' => 'Salary components and structures, payroll runs, payslips, loans and posting salaries to the ledger.'],
            ['name' => 'Use Tasks', 'slug' => 'use-tasks', 'group' => 'communication', 'description' => 'Task boards, My Tasks, assigning and commenting on tasks.'],
            ['name' => 'Use Staff Chat', 'slug' => 'use-chat', 'group' => 'communication', 'description' => 'One-to-one and group chat between employees.'],
            ['name' => 'Manage Library', 'slug' => 'manage-library', 'group' => 'library', 'description' => 'Book catalogue and copies, issuing and returning books, library fines.'],
            ['name' => 'Manage Assets', 'slug' => 'manage-assets', 'group' => 'assets', 'description' => 'Asset register, categories, locations, repairs, verification, depreciation and disposal.'],
            ['name' => 'Assign Assets', 'slug' => 'assign-assets', 'group' => 'assets', 'description' => 'Hand assets to staff, students, departments or rooms and take them back.'],
            ['name' => 'Manage Expenses', 'slug' => 'manage-expenses', 'group' => 'accounts', 'description' => 'Record and pay expenses and vendor bills; approving them needs Approve Requests.'],
            ['name' => 'Reconcile Bank', 'slug' => 'reconcile-bank', 'group' => 'accounts', 'description' => 'Match bank statements against the bank accounts in the ledger.'],
            ['name' => 'Manage Counselling', 'slug' => 'manage-counselling', 'group' => 'academics', 'description' => 'Record counselling sessions with students, follow-ups and confidential notes.'],
            ['name' => 'Manage Appraisals', 'slug' => 'manage-appraisals', 'group' => 'hr', 'description' => 'Appraisal cycles and criteria, launching reviews, reassigning reviewers and seeing every result.'],
            ['name' => 'Manage Transport', 'slug' => 'manage-transport', 'group' => 'transport', 'description' => 'Vehicles, routes, stops and which students ride the school buses.'],
            ['name' => 'Manage Inventory', 'slug' => 'manage-inventory', 'group' => 'inventory', 'description' => 'Items, stock, purchase orders, goods receipts, transfers and stock counts.'],
            ['name' => 'Issue Stock', 'slug' => 'issue-stock', 'group' => 'inventory', 'description' => 'Issue stock to staff, students, departments or rooms, sell to students and take returns.'],
            ['name' => 'Monitor Staff Chat', 'slug' => 'monitor-chat', 'group' => 'communication', 'description' => 'Read every staff chat without being seen, get told about new groups, and add or remove group members.'],
            ['name' => 'Manage Fee Setup', 'slug' => 'manage-fee-setup', 'group' => 'accounts', 'description' => 'Fee heads, class fee structures, concession types and student concessions.'],
        ], ['slug'], ['name', 'group', 'description']);
    }
}
