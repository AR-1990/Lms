<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ConcessionType;
use App\Models\FeeStructure;
use App\Models\WebsiteFaq;
use App\Models\WebsitePost;
use App\Models\WebsiteStat;
use App\Models\WebsiteTestimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

/**
 * Public website content (stats, testimonials, news & notices, FAQs): admin CRUD
 * plus the published lists the website pages read.
 */
class WebsiteContentService
{
    /**
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'stats' => WebsiteStat::class,
        'testimonials' => WebsiteTestimonial::class,
        'posts' => WebsitePost::class,
        'faqs' => WebsiteFaq::class,
    ];

    /**
     * @return class-string<Model>
     */
    public function modelFor(string $type): string
    {
        return self::TYPES[$type] ?? abort(404);
    }

    public function find(string $type, int $id): Model
    {
        return $this->modelFor($type)::query()->with($type === 'posts' ? ['category:id,name'] : [])->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(string $type): array
    {
        $query = $this->modelFor($type)::query();

        if ($type === 'posts') {
            $query->with('category:id,name');
        }

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->addColumn('category_name', fn (Model $record): ?string => $record instanceof WebsitePost ? $record->category?->name : null)
            ->addColumn('image_url', fn (Model $record): ?string => $record instanceof WebsitePost ? $record->imageUrl() : null)
            ->editColumn('published_on', fn (Model $record): ?string => $record instanceof WebsitePost ? $record->published_on?->toDateString() : null)
            ->removeColumn('category')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $type, ?Model $record, array $data): Model
    {
        if ($type === 'posts') {
            $data['slug'] = Str::slug($data['title']);
        }

        return DB::transaction(function () use ($type, $record, $data): Model {
            if ($record === null) {
                $record = $this->modelFor($type)::create($data);
            } else {
                $record->update($data);
            }

            return $record->refresh();
        });
    }

    public function delete(Model $record): void
    {
        DB::transaction(fn () => $record->delete());
    }

    /**
     * Replace a post's picture; the old upload stays on disk (nothing is permanently removed).
     */
    public function uploadPostImage(WebsitePost $post, UploadedFile $image): WebsitePost
    {
        $post->update(['image_path' => $image->store('website', 'public')]);

        return $post;
    }

    /* ---------- What the public site shows ---------- */

    /**
     * @return Collection<int, WebsiteStat>
     */
    public function stats(): Collection
    {
        return WebsiteStat::where('is_active', true)->orderBy('sort_order')->get(['label', 'value', 'suffix', 'icon']);
    }

    /**
     * @return Collection<int, WebsiteTestimonial>
     */
    public function testimonials(): Collection
    {
        return WebsiteTestimonial::where('is_active', true)->orderBy('sort_order')->get(['name', 'role_title', 'quote', 'photo_path']);
    }

    /**
     * @return Collection<int, WebsiteFaq>
     */
    public function faqs(): Collection
    {
        return WebsiteFaq::where('is_active', true)->orderBy('sort_order')->get(['question', 'answer']);
    }

    public function publishedPosts(): Builder
    {
        return WebsitePost::query()
            ->with('category:id,name')
            ->where('is_published', true)
            ->where(fn (Builder $query) => $query->whereNull('published_on')->orWhereDate('published_on', '<=', now()))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_on');
    }

    /**
     * @return Collection<int, WebsitePost>
     */
    public function ticker(): Collection
    {
        return $this->publishedPosts()->where('show_in_ticker', true)->reorder()->orderByDesc('published_on')->limit(5)->get(['id', 'category_id', 'title']);
    }

    /**
     * Fee estimator rows for the current session (paisa): monthly mandatory charges, one-time
     * registration + admission charges, and optional monthly services such as transport.
     *
     * @return Collection<int, FeeStructure>
     */
    public function feeEstimates(): Collection
    {
        return FeeStructure::query()
            ->join('classes', 'classes.id', '=', 'fee_structures.class_id')
            ->where('fee_structures.academic_session_id', AcademicSession::where('is_current', true)->value('id'))
            ->where('fee_structures.is_active', true)
            ->orderBy('classes.level_order')
            ->select(['fee_structures.id', 'classes.id as class_id', 'classes.name as class_name'])
            ->withSum(['items as monthly_total' => fn (Builder $query) => $query->where('charge_on', 'recurring')->where('is_mandatory', true)], 'amount')
            ->withSum(['items as one_time_total' => fn (Builder $query) => $query->whereIn('charge_on', ['on_application', 'on_admission'])->where('is_mandatory', true)], 'amount')
            ->withSum(['items as optional_total' => fn (Builder $query) => $query->where('charge_on', 'recurring')->where('is_mandatory', false)], 'amount')
            ->get();
    }

    /**
     * Percent concessions a family can tick in the estimator (value is percent × 100).
     *
     * @return Collection<int, ConcessionType>
     */
    public function estimatorConcessions(): Collection
    {
        return ConcessionType::where('is_active', true)->where('calculation', 'percent')->orderBy('name')->get(['id', 'name', 'default_value']);
    }
}
