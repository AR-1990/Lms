<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Announcements: write → publish now or on a schedule → people see them in the portal / app, get a bell
 * notification (and SMS / email when ticked); public ones also show on the website. Tracks reads.
 */
class AnnouncementService
{
    public function __construct(private NotificationService $notificationService, private ActivityLogger $activityLogger) {}

    /**
     * @param  array{status?: string|null, category_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        // select() must come before withCount(), or it replaces the count columns.
        $query = Announcement::query()
            ->select(['id', 'title', 'category_id', 'summary', 'status', 'is_public', 'is_pinned', 'is_urgent', 'publish_at', 'expires_at', 'requires_acknowledgement', 'views_count'])
            ->with(['category:id,name,color', 'audiences:id,announcement_id,audience_type,audience_id'])
            ->withCount(['reads', 'reads as acknowledged_count' => fn (Builder $inner) => $inner->whereNotNull('acknowledged_at')])
            ->when($filters['status'] ?? null, fn (Builder $inner, string $status) => $inner->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $inner, int $categoryId) => $inner->where('category_id', $categoryId));

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (Announcement $announcement): ?string => $announcement->category?->name)
            ->addColumn('category_color', fn (Announcement $announcement): ?string => $announcement->category?->color)
            ->addColumn('audience_types', fn (Announcement $announcement): string => $announcement->audiences->pluck('audience_type')->unique()->implode(', '))
            ->editColumn('publish_at', fn (Announcement $announcement): ?string => $announcement->publish_at?->toIso8601String())
            ->removeColumn('category', 'audiences')
            ->toArray();
    }

    public function find(int $id): Announcement
    {
        return Announcement::with(['category:id,name,color,icon', 'audiences:id,announcement_id,audience_type,audience_id', 'publisher:id,name'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data  announcement fields + audiences[{audience_type, audience_id}]
     */
    public function save(?Announcement $announcement, array $data): Announcement
    {
        if ($announcement?->status === 'archived') {
            throw ValidationException::withMessages(['status' => ['An archived announcement cannot be changed.']]);
        }

        $audiences = collect($data['audiences'] ?? [])->unique(fn (array $row): string => $row['audience_type'].'-'.($row['audience_id'] ?? ''));
        $data = collect($data)->except('audiences')->put('slug', Str::slug($data['title']))->all();

        return DB::transaction(function () use ($announcement, $data, $audiences): Announcement {
            $announcement === null ? $announcement = Announcement::create([...$data, 'status' => 'draft']) : $announcement->update($data);

            $announcement->audiences()->get()->each->delete();

            foreach ($audiences as $row) {
                $announcement->audiences()->create([
                    'audience_type' => $row['audience_type'],
                    'audience_id' => str_starts_with($row['audience_type'], 'all_') || $row['audience_type'] === 'everyone' ? null : $row['audience_id'],
                ]);
            }

            return $this->find($announcement->id);
        });
    }

    /**
     * Publish now, or mark scheduled when publish_at is in the future (the scheduler publishes it then).
     */
    public function publish(Announcement $announcement, User $user): Announcement
    {
        if (! in_array($announcement->status, ['draft', 'scheduled'], true)) {
            throw ValidationException::withMessages(['status' => ["This announcement is already {$announcement->status}."]]);
        }

        if ($announcement->audiences()->doesntExist() && ! $announcement->is_public) {
            throw ValidationException::withMessages(['audiences' => ['Choose who should see it, or tick "Show on the website".']]);
        }

        return DB::transaction(function () use ($announcement, $user): Announcement {
            if ($announcement->publish_at?->isFuture()) {
                $announcement->update(['status' => 'scheduled', 'published_by' => $user->id]);
                $this->activityLogger->record('announcements', 'scheduled', "{$user->name} scheduled \"{$announcement->title}\" for {$announcement->publish_at->format('d M Y h:i A')}", $announcement);

                return $announcement;
            }

            $announcement->update(['status' => 'published', 'published_by' => $user->id, 'publish_at' => $announcement->publish_at ?? now()]);
            $sent = $this->deliver($announcement);
            $this->activityLogger->record('announcements', 'published', "{$user->name} published \"{$announcement->title}\" to {$sent} people", $announcement);

            return $announcement;
        });
    }

    public function archive(Announcement $announcement): Announcement
    {
        DB::transaction(function () use ($announcement): void {
            $announcement->update(['status' => 'archived']);
            $this->activityLogger->record('announcements', 'archived', (auth()->user()?->name ?? 'System')." archived \"{$announcement->title}\"", $announcement);
        });

        return $announcement;
    }

    /**
     * Scheduler: publish what is due and archive what has expired. Returns [published, archived].
     *
     * @return array{0: int, 1: int}
     */
    public function publishDue(): array
    {
        $due = Announcement::where('status', 'scheduled')->where('publish_at', '<=', now())->get();

        foreach ($due as $announcement) {
            DB::transaction(function () use ($announcement): void {
                $announcement->update(['status' => 'published']);
                $sent = $this->deliver($announcement);
                $this->activityLogger->record('announcements', 'published', "Scheduled announcement \"{$announcement->title}\" published to {$sent} people", $announcement);
            });
        }

        $expired = Announcement::where('status', 'published')->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
        $expired->each(fn (Announcement $announcement) => $this->archive($announcement));

        return [$due->count(), $expired->count()];
    }

    public function delete(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $announcement->audiences()->get()->each->delete();
            $announcement->delete();
        });
    }

    /**
     * Read counts for the "read by 82%" figure.
     *
     * @return array{recipients: int, read: int, acknowledged: int}
     */
    public function stats(Announcement $announcement): array
    {
        return [
            'recipients' => count($this->recipientIds($announcement)),
            'read' => $announcement->reads()->count(),
            'acknowledged' => $announcement->reads()->whereNotNull('acknowledged_at')->count(),
        ];
    }

    /* ---------- Who sees it ---------- */

    /**
     * Every user an announcement reaches (for the bell, SMS and email).
     *
     * @return array<int, int>
     */
    public function recipientIds(Announcement $announcement): array
    {
        $sessionId = AcademicSession::where('is_current', true)->value('id');
        $ids = collect();

        foreach ($announcement->audiences()->get() as $audience) {
            $ids = $ids->merge(match ($audience->audience_type) {
                'everyone' => User::where('is_active', true)->pluck('id'),
                'all_students' => $this->roleUserIds('student'),
                'all_parents' => $this->roleUserIds('parent'),
                'all_employees' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->whereNotNull('user_id')->pluck('user_id'),
                'role' => DB::table('role_user')->where('role_id', $audience->audience_id)->pluck('user_id'),
                'department' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->where('department_id', $audience->audience_id)->whereNotNull('user_id')->pluck('user_id'),
                'class', 'section' => $this->notificationService->familyUserIds(Enrollment::where('academic_session_id', $sessionId)->where('status', 'active')
                    ->where($audience->audience_type === 'class' ? 'class_id' : 'section_id', $audience->audience_id)->pluck('student_id')),
                default => collect([$audience->audience_id]),
            });
        }

        return $ids->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * Announcements this user may see (portal "Notices" and the mobile app), pinned first.
     */
    public function feedFor(User $user): Builder
    {
        $sessionId = AcademicSession::where('is_current', true)->value('id');
        $roleSlugs = $user->roles()->pluck('slug');
        $employee = Employee::where('user_id', $user->id)->whereIn('status', Employee::CURRENT_STATUSES)->first(['id', 'department_id']);
        $enrollments = Enrollment::where('academic_session_id', $sessionId)->whereIn('student_id', Student::ownIdsFor($user))->get(['class_id', 'section_id']);

        $targets = [
            'role' => $user->roles()->pluck('roles.id')->all(),
            'class' => $enrollments->pluck('class_id')->unique()->all(),
            'section' => $enrollments->pluck('section_id')->unique()->all(),
            'department' => $employee?->department_id ? [$employee->department_id] : [],
            'user' => [$user->id],
        ];
        $groups = array_values(array_filter([
            'everyone',
            $roleSlugs->contains('student') ? 'all_students' : null,
            $roleSlugs->contains('parent') ? 'all_parents' : null,
            $employee ? 'all_employees' : null,
        ]));

        return Announcement::query()
            ->live()
            ->with(['category:id,name,color,icon', 'reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->whereHas('audiences', function (Builder $query) use ($groups, $targets): void {
                $query->where(function (Builder $inner) use ($groups, $targets): void {
                    $inner->whereIn('audience_type', $groups);

                    foreach (array_filter($targets) as $type => $ids) {
                        $inner->orWhere(fn (Builder $match) => $match->where('audience_type', $type)->whereIn('audience_id', $ids));
                    }
                });
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('publish_at');
    }

    /**
     * Record that the user opened (and optionally acknowledged) an announcement they can see.
     */
    public function markRead(int $announcementId, User $user, ?string $device, bool $acknowledge): AnnouncementRead
    {
        $announcement = $this->feedFor($user)->findOrFail($announcementId);

        return DB::transaction(function () use ($announcement, $user, $device, $acknowledge): AnnouncementRead {
            $read = AnnouncementRead::firstOrNew(['announcement_id' => $announcement->id, 'user_id' => $user->id]);

            if (! $read->exists) {
                $read->fill(['read_at' => now(), 'device' => $device]);
                $announcement->increment('views_count');
            }

            if ($acknowledge && $read->acknowledged_at === null) {
                $read->acknowledged_at = now();
                $this->activityLogger->record('announcements', 'acknowledged', "{$user->name} acknowledged \"{$announcement->title}\"", $announcement);
            }

            $read->save();

            return $read;
        });
    }

    /**
     * Public announcements for the website (news page and ticker).
     *
     * @return Collection<int, Announcement>
     */
    public function publicAnnouncements(int $limit = 20): Collection
    {
        return Announcement::query()->live()->where('is_public', true)->with('category:id,name')
            ->orderByDesc('is_pinned')->orderByDesc('publish_at')->limit($limit)
            ->get(['id', 'title', 'slug', 'category_id', 'summary', 'cover_image_path', 'is_pinned', 'is_urgent', 'publish_at']);
    }

    /* ---------- Categories ---------- */

    /**
     * @return array<string, mixed>
     */
    public function categoriesDatatable(): array
    {
        return DataTables::eloquent(AnnouncementCategory::query()->withCount('announcements'))->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCategory(?AnnouncementCategory $category, array $data): AnnouncementCategory
    {
        return DB::transaction(function () use ($category, $data): AnnouncementCategory {
            if ($category === null) {
                return AnnouncementCategory::create($data);
            }

            $category->update($data);

            return $category;
        });
    }

    public function deleteCategory(AnnouncementCategory $category): void
    {
        if ($category->announcements()->exists()) {
            throw ValidationException::withMessages(['category' => ['Announcements use this category; make it inactive instead.']]);
        }

        DB::transaction(fn () => $category->delete());
    }

    /**
     * @return SupportCollection<int, int>
     */
    private function roleUserIds(string $slug): SupportCollection
    {
        return DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('roles.slug', $slug)->pluck('role_user.user_id');
    }

    private function deliver(Announcement $announcement): int
    {
        $channels = array_merge($announcement->send_push ? ['in_app', 'push'] : [], $announcement->send_sms ? ['sms'] : [], $announcement->send_email ? ['email'] : []);

        if ($channels === []) {
            return 0;
        }

        return $this->notificationService->notify(
            $this->recipientIds($announcement), 'announcement', $announcement->title, $announcement->summary ?? Str::limit(strip_tags($announcement->body), 300),
            route('notices', ['announcement' => $announcement->id]), $announcement, $announcement->is_urgent, $channels,
        );
    }
}
