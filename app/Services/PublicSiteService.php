<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\CalendarEvent;
use App\Models\SchoolClass;
use App\Models\WebsitePost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Public website content shaped once for both the website pages and the public API (/api/site) that the
 * mobile apps show to visitors: home blocks, news and events, contact details. No sign-in needed.
 */
class PublicSiteService
{
    public function __construct(
        private WebsiteContentService $websiteService,
        private CalendarService $calendarService,
        private AnnouncementService $announcementService,
        private LookupService $lookupService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        return [
            'stats' => $this->websiteService->stats()->map(fn ($stat): array => ['icon' => $stat->icon, 'target' => $stat->value, 'suffix' => $stat->suffix, 'label' => $stat->label])->values()->all(),
            'announcements' => $this->announcementService->publicAnnouncements(5)->map(fn (Announcement $announcement): array => ['tag' => $announcement->category?->name ?? 'Notice', 'text' => $announcement->title])
                ->concat($this->websiteService->ticker()->map(fn (WebsitePost $post): array => ['tag' => $post->category?->name ?? 'Notice', 'text' => $post->title]))
                ->take(6)->values()->all(),
            'testimonials' => $this->websiteService->testimonials()->map(fn ($item): array => ['name' => $item->name, 'role' => $item->role_title, 'quote' => $item->quote, 'initials' => $item->initials()])->values()->all(),
            'upcoming_events' => $this->events(),
            'fee_estimates' => $this->websiteService->feeEstimates(),
            'estimator_concessions' => $this->websiteService->estimatorConcessions(),
            'classes' => $this->classes(),
        ];
    }

    /**
     * Public notices and published posts, newest first, plus the coming events.
     *
     * @return array{articles: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>}
     */
    public function news(): array
    {
        $notices = $this->announcementService->publicAnnouncements()->map(fn (Announcement $announcement): array => [
            'id' => $announcement->id,
            'type' => 'notice',
            'category' => $announcement->category?->name ?? 'Notice',
            'title' => $announcement->title,
            'excerpt' => $announcement->summary,
            'date' => $announcement->publish_at?->format('F d, Y'),
            'read_time' => null,
            'image' => $announcement->cover_image_path ? asset('storage/'.$announcement->cover_image_path) : asset('images/hero-campus.jpg'),
            'featured' => (bool) $announcement->is_pinned,
        ]);

        return [
            'articles' => $notices->concat($this->websiteService->publishedPosts()->get()->map(fn (WebsitePost $post): array => [
                'id' => $post->id,
                'type' => 'post',
                'category' => $post->category?->name,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'date' => $post->published_on?->format('F d, Y'),
                'read_time' => $post->read_minutes ? $post->read_minutes.' min read' : null,
                'image' => $post->imageUrl() ?? asset('images/hero-campus.jpg'),
                'featured' => (bool) $post->is_featured,
            ]))->values()->all(),
            'events' => $this->events(),
        ];
    }

    /**
     * @return array{faqs: Collection, topics: Collection, classes: Collection}
     */
    public function contact(): array
    {
        return [
            'faqs' => $this->websiteService->faqs(),
            'topics' => $this->lookupService->options('contact_topic'),
            'classes' => $this->classes(),
        ];
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    public function classes(): Collection
    {
        return SchoolClass::where('is_active', true)->orderBy('level_order')->get(['id', 'name']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function events(): array
    {
        return $this->calendarService->upcomingPublic()->map(fn (CalendarEvent $event): array => [
            'date' => $event->starts_on->toDateString(),
            'day' => $event->starts_on->format('d'),
            'month' => $event->starts_on->format('M'),
            'title' => $event->title,
            'time' => $event->start_time ? Carbon::parse($event->start_time)->format('h:i A').($event->end_time ? ' - '.Carbon::parse($event->end_time)->format('h:i A') : '') : 'All day',
            'venue' => $event->venue,
            'description' => $event->description,
        ])->values()->all();
    }
}
