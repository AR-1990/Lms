@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

@if ($section = $page->section('steps'))
<!-- Admission Roadmap -->
<section class="section section-white">
    <div class="container">
        @include('partials.section-header', ['section' => $section])

        <div class="grid-4">
            @foreach ($section->items as $step)
                <div class="glow-card" data-reveal="fade-up" style="padding: 2.25rem 1.75rem; transition-delay: {{ $loop->index * 0.1 }}s;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: {{ ['amber' => 'var(--amber)', 'emerald' => 'var(--emerald)'][$step->tone] ?? 'var(--primary)' }}; font-family: var(--font-heading); margin-bottom: 0.5rem; line-height: 1;">
                        {{ $step->value ?: str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">{{ $step->title }}</h4>
                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6;">{{ $step->body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Counselor Consultation + Online Admission Form -->
<section class="section section-light-alt">
    <div class="container">
        <div class="grid-2" style="align-items: flex-start; gap: 3.5rem;">
            @if ($counselor = $page->section('counselor'))
                <div data-reveal="fade-right">
                    @if ($counselor->image_path)
                        <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl); margin-bottom: 2rem;">
                            <img src="{{ $counselor->imageUrl() }}" alt="{{ $counselor->title }}" style="width: 100%; height: auto;">
                        </div>
                    @endif

                    <span class="section-subtitle">{{ $counselor->subtitle }}</span>
                    <h3 style="font-size: 1.75rem; margin-bottom: 1rem;">{{ $counselor->title }}</h3>
                    <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">{{ $counselor->description }}</p>

                    <div style="background: #ffffff; border-radius: var(--radius-lg); padding: 1.5rem; border: 1px solid var(--border-color); display: flex; gap: 1.25rem; align-items: center;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            @include('partials.site-icon', ['name' => 'phone', 'size' => 24])
                        </div>
                        <div>
                            <div style="font-weight: 800; font-size: 1.05rem;">{{ $counselor->image_caption ?: 'Admissions Direct Line' }}</div>
                            <div style="font-size: 0.9rem; color: var(--primary); font-weight: 700;">{{ collect([$settings->admissions_phone, $settings->school_phone])->filter()->unique()->implode(' / ') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            @php($form = $page->section('form'))
            <div data-reveal="fade-left">
                <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); background: #ffffff;">
                    @if ($form?->badge)<span class="badge badge-amber" style="margin-bottom: 0.75rem;">{{ $form->badge }}</span>@endif
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.45rem;">{{ $form?->title ?? 'Submit Online Application' }}</h3>
                    <p style="font-size: 0.925rem; margin-bottom: 1.5rem;">{{ $form?->description }}</p>

                    <form data-ajax-inquiry action="{{ route('inquiry.submit') }}" method="POST">
                        @csrf
                        <div class="grid-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="admStudentName">Student's Full Name</label>
                                <input type="text" id="admStudentName" name="child_name" class="form-control" placeholder="e.g. Daniyal Khan" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="admParentName">Parent / Guardian Name</label>
                                <input type="text" id="admParentName" name="parent_name" class="form-control" placeholder="e.g. Imran Khan" required>
                            </div>
                        </div>

                        <div class="grid-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="admPhone">Parent WhatsApp / Phone</label>
                                <input type="tel" id="admPhone" name="phone" class="form-control" placeholder="0300-XXXXXXX" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="admEmail">Parent Email Address</label>
                                <input type="email" id="admEmail" name="email" class="form-control" placeholder="parent@email.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admGrade">Class Applying For</label>
                            <select id="admGrade" name="class_sought_id" class="form-control">
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admMessage">Previous School & Any Special Notes</label>
                            <textarea id="admMessage" name="message" class="form-control" rows="3" placeholder="Previous school name, current grade, and any special inquiries..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Submit Admission Application
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
