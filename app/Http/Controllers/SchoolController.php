<?php

namespace App\Http\Controllers;

use App\Models\Lookup;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AdmissionService;
use App\Services\CardService;
use App\Services\PortalAccessService;
use App\Services\PublicSiteService;
use App\Services\WebsitePageService;
use App\Support\ApiResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function __construct(
        private PortalAccessService $portalAccessService,
        private ActivityLogger $activityLogger,
        private WebsitePageService $pageService,
        private PublicSiteService $siteService,
    ) {}

    /**
     * Homepage with Hero, Wings, Metrics, Testimonials, and Announcements
     */
    public function home(): View
    {
        $home = $this->siteService->home();

        return view('pages.home', [
            'page' => $this->pageService->page('home'),
            'classes' => $home['classes'],
            'stats' => $home['stats'],
            'announcements' => $home['announcements'],
            'upcomingEvents' => $home['upcoming_events'],
            'testimonials' => $home['testimonials'],
            'feeEstimates' => $home['fee_estimates'],
            'estimatorConcessions' => $home['estimator_concessions'],
        ]);
    }

    /**
     * About Us Page: Vision, Mission, Leadership, Values
     */
    public function about(): View
    {
        return view('pages.about', ['page' => $this->pageService->page('about')]);
    }

    /**
     * Academics Page: Curriculum, Wings, STEM & Co-Curricular
     */
    public function academics(): View
    {
        return view('pages.academics', ['page' => $this->pageService->page('academics')]);
    }

    /**
     * Admissions Page: Process, Fee Calculator, Requirements
     */
    public function admissions(): View
    {
        return view('pages.admissions', ['page' => $this->pageService->page('admissions'), 'classes' => $this->siteService->classes()]);
    }

    /**
     * Campus & Facilities Page: Labs, Library, Sports, Safety
     */
    public function campus(): View
    {
        return view('pages.campus', ['page' => $this->pageService->page('campus')]);
    }

    /**
     * News & Events Page: Announcements, Sports Gala, STEM Competitions
     */
    public function news(): View
    {
        $news = $this->siteService->news();

        return view('pages.news', ['page' => $this->pageService->page('news'), 'articles' => $news['articles'], 'calendar' => $news['events']]);
    }

    /**
     * Contact Us & Inquiry Page
     */
    public function contact(): View
    {
        return view('pages.contact', ['page' => $this->pageService->page('contact'), ...$this->siteService->contact()]);
    }

    /**
     * LMS & Portals Gateway Hub (Ready for upcoming LMS & Accounts modules)
     */
    public function portal(): View
    {
        return view('pages.portal', ['page' => $this->pageService->page('portal')]);
    }

    /**
     * Dedicated Login Page for Portals
     */
    public function login(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('pages.login', ['page' => $this->pageService->page('login')]);
    }

    public function forgotPassword(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('pages.password', ['mode' => 'forgot']);
    }

    /**
     * The page the emailed reset link opens (the link carries the token and the account's email).
     */
    public function resetPassword(Request $request, string $token): View
    {
        return view('pages.password', ['mode' => 'reset', 'token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    /**
     * Authenticate portal credentials and send the user to their role dashboard.
     */
    public function handleLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:4',
        ]);

        $account = User::findForLogin($validated['username']);

        if (! $account || ! Auth::attempt(
            ['email' => $account->email, 'password' => $validated['password']],
            $request->boolean('remember')
        )) {
            $this->activityLogger->record('auth', 'login_failed', "Failed sign-in attempt for \"{$validated['username']}\"");

            return back()
                ->withErrors(['username' => 'These credentials do not match our records.'])
                ->onlyInput('username');
        }

        if (! $account->is_active) {
            $this->activityLogger->record('auth', 'login_failed', "{$account->name} tried to sign in to a disabled account", $account, actor: $account);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['username' => 'This account has been disabled. Please contact the school office.'])
                ->onlyInput('username');
        }

        $user = Auth::user()->load('roles');
        // The account's own roles pick its portal; there is no portal picker on the sign-in page.
        $portal = $this->portalAccessService->resolveSelectedPortal($user);

        if ($portal === null) {
            $this->activityLogger->record('auth', 'login_failed', "{$user->name} has no portal assigned", $user, actor: $user);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['username' => 'This account has no portal yet. Please contact the school office.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put('portal', $portal);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->activityLogger->record('auth', 'login', "{$user->name} signed in to the {$portal} portal", $user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Sign the user out of the portal session.
     */
    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            $this->activityLogger->record('auth', 'logout', "{$request->user()->name} signed out", $request->user());
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Handle Inquiry Submissions
     */
    /**
     * Gate kiosk screen: camera scanner over the kiosk API (the token in the link is checked there).
     */
    public function kiosk(string $token): View
    {
        return view('pages.kiosk', ['token' => $token]);
    }

    /**
     * Public page opened by an ID card's QR code.
     */
    public function verifyCard(string $token, CardService $cardService): View
    {
        return view('pages.verify-card', ['card' => $cardService->verify($token)]);
    }

    /**
     * Website contact / admission forms create a real admission inquiry (source: Website).
     */
    public function submitInquiry(Request $request, AdmissionService $admissionService): JsonResponse
    {
        $validated = $request->validate([
            'parent_name' => ['required', 'string', 'max:255'],
            'child_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'class_sought_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'topic_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'contact_topic')->whereNull('deleted_at')],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'parent_name.required' => 'Please tell us your name.',
            'phone.required' => 'Please give a number we can call you on.',
            'phone.regex' => 'Enter a valid phone number, e.g. 03001234567.',
            'email.email' => 'That email address does not look right.',
            'message.required' => 'Please write a short message.',
        ]);

        $topic = isset($validated['topic_id']) ? Lookup::whereKey($validated['topic_id'])->value('name') : null;

        $admissionService->saveInquiry(null, [
            'parent_name' => $validated['parent_name'],
            'child_name' => $validated['child_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'class_sought_id' => $validated['class_sought_id'] ?? null,
            'source_id' => Lookup::where('type', 'inquiry_source')->where('name', 'Website')->value('id'),
            'message' => ($topic ? "[{$topic}] " : '').$validated['message'],
        ]);

        return ApiResponseHelper::success(null, 'Thank you! Your inquiry is with our admissions office; we will call you shortly.', 201);
    }
}
