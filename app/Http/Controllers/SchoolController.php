<?php

namespace App\Http\Controllers;

use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function __construct(private PortalAccessService $portalAccessService) {}

    /**
     * Homepage with Hero, Wings, Metrics, Testimonials, and Announcements
     */
    public function home()
    {
        $stats = [
            ['icon' => 'graduation-cap', 'target' => 2500, 'suffix' => '+', 'label' => 'Enrolled Students'],
            ['icon' => 'award', 'target' => 100, 'suffix' => '%', 'label' => 'Matric / O-Level Pass Rate'],
            ['icon' => 'users', 'target' => 120, 'suffix' => '+', 'label' => 'Certified Educators'],
            ['icon' => 'building', 'target' => 28, 'suffix' => '+', 'label' => 'Years of Academic Legacy'],
        ];

        $announcements = [
            ['tag' => 'Admissions', 'text' => 'Spring 2026 Admissions Open for Pre-School to Grade 10 - Early bird fee discounts apply.'],
            ['tag' => 'Achievement', 'text' => 'Pinnacle Robotics Team secured 1st place in National STEM Championship 2026.'],
            ['tag' => 'Notice', 'text' => 'Parent-Teacher Meeting (PTM) for Mid-Term assessments scheduled for next Saturday.'],
        ];

        $upcomingEvents = [
            ['day' => '15', 'month' => 'Sep', 'title' => 'Annual Science & Tech Fair 2026', 'time' => '09:00 AM - 02:00 PM', 'venue' => 'Main Auditorium', 'image' => 'facility-science-lab.jpg'],
            ['day' => '22', 'month' => 'Sep', 'title' => 'Inter-School Sports Gala & Track Events', 'time' => '08:30 AM - 01:30 PM', 'venue' => 'Sports Complex', 'image' => 'sports-gala.jpg'],
            ['day' => '05', 'month' => 'Oct', 'title' => 'Parent-Teacher Review Conference', 'time' => '10:00 AM - 03:00 PM', 'venue' => 'Campus Auditorium', 'image' => 'admissions-meeting.jpg'],
        ];

        $testimonials = [
            [
                'name' => 'Dr. Farhan Qureshi',
                'role' => 'Parent of Grade 8 & 10 Students',
                'quote' => 'Pinnacle Academy has provided the best academic balance for both of my children. Their STEM program and focus on ethical character building have truly helped them thrive.',
                'initials' => 'FQ',
            ],
            [
                'name' => 'Ayesha Mansoor',
                'role' => 'Parent of Montessori Student',
                'quote' => 'The warmth of the early years teachers and the safe, caring environment gave our daughter the smoothest start to school life. Highly recommend to every parent!',
                'initials' => 'AM',
            ],
            [
                'name' => 'Bilal Tariq',
                'role' => 'Alumni (Class of 2023 - Now at LUMS)',
                'quote' => 'The conceptual foundation in sciences and public speaking mentorship at Pinnacle prepared me exceptionally well for university life and competitive exams.',
                'initials' => 'BT',
            ],
        ];

        return view('pages.home', compact('stats', 'announcements', 'upcomingEvents', 'testimonials'));
    }

    /**
     * About Us Page: Vision, Mission, Leadership, Values
     */
    public function about()
    {
        return view('pages.about');
    }

    /**
     * Academics Page: Curriculum, Wings, STEM & Co-Curricular
     */
    public function academics()
    {
        return view('pages.academics');
    }

    /**
     * Admissions Page: Process, Fee Calculator, Requirements
     */
    public function admissions()
    {
        return view('pages.admissions');
    }

    /**
     * Campus & Facilities Page: Labs, Library, Sports, Safety
     */
    public function campus()
    {
        return view('pages.campus');
    }

    /**
     * News & Events Page: Announcements, Sports Gala, STEM Competitions
     */
    public function news()
    {
        $articles = [
            [
                'id' => 1,
                'category' => 'Achievements',
                'title' => 'Pinnacle Robotics Team Triumphs at National STEM Olympiad 2026',
                'excerpt' => 'Our student engineering squad secured first place with their autonomous smart agricultural rover prototype among 60 participating institutions.',
                'date' => 'August 24, 2026',
                'read_time' => '3 min read',
                'image' => 'hero-robotics.jpg',
                'featured' => true,
            ],
            [
                'id' => 2,
                'category' => 'Sports',
                'title' => 'Annual Inter-School Athletics Championship & Sports Gala 2026',
                'excerpt' => 'Over 400 student athletes competed across track, football, and basketball tournaments in our 5-acre sports complex.',
                'date' => 'August 18, 2026',
                'read_time' => '4 min read',
                'image' => 'sports-gala.jpg',
                'featured' => false,
            ],
            [
                'id' => 3,
                'category' => 'Academics',
                'title' => '100% A+ & A Grades Secured in Cambridge & Federal Board Examinations',
                'excerpt' => 'Pinnacle students once again proved their academic brilliance with historic merit positions across science and computer streams.',
                'date' => 'August 10, 2026',
                'read_time' => '2 min read',
                'image' => 'hero-leadership.jpg',
                'featured' => false,
            ],
            [
                'id' => 4,
                'category' => 'Campus Life',
                'title' => 'Expansion of The Learning Commons & 4K Smart Classroom Suites',
                'excerpt' => 'Academy inaugurates new interactive digital touchscreens and over 5,000 international scientific literature additions to the central library.',
                'date' => 'July 28, 2026',
                'read_time' => '3 min read',
                'image' => 'facility-library.jpg',
                'featured' => false,
            ],
            [
                'id' => 5,
                'category' => 'Early Years',
                'title' => 'Montessori Sensorial Discovery Week & Creative Exhibition',
                'excerpt' => 'Early years children showcased practical life experiments, phonics spelling bees, and colorful artistic models for visiting parents.',
                'date' => 'July 15, 2026',
                'read_time' => '2 min read',
                'image' => 'montessori-kids.jpg',
                'featured' => false,
            ],
            [
                'id' => 6,
                'category' => 'Admissions',
                'title' => 'Admissions Open for Session 2026-27 & Merit Scholarship Tests',
                'excerpt' => 'Online registration is now active for Playgroup through Grade 11 with up to 100% merit scholarship slots available.',
                'date' => 'July 01, 2026',
                'read_time' => '3 min read',
                'image' => 'admissions-meeting.jpg',
                'featured' => false,
            ],
        ];

        return view('pages.news', compact('articles'));
    }

    /**
     * Contact Us & Inquiry Page
     */
    public function contact()
    {
        return view('pages.contact');
    }

    /**
     * LMS & Portals Gateway Hub (Ready for upcoming LMS & Accounts modules)
     */
    public function portal()
    {
        return view('pages.portal');
    }

    /**
     * Dedicated Login Page for Portals
     */
    public function login(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $role = $request->query('role', 'student');

        if (! in_array($role, $this->portalAccessService->getPortalKeys(), true)) {
            $role = 'student';
        }

        return view('pages.login', compact('role'));
    }

    /**
     * Authenticate portal credentials and send the user to their role dashboard.
     */
    public function handleLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($this->portalAccessService->getPortalKeys())],
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:4',
        ]);

        $email = $this->resolveLoginEmail($validated['username']);

        if (! Auth::attempt(
            ['email' => $email, 'password' => $validated['password']],
            $request->boolean('remember')
        )) {
            return back()
                ->withErrors(['username' => 'These credentials do not match our records.'])
                ->onlyInput('username', 'role');
        }

        $request->session()->regenerate();
        $request->session()->put('portal', $validated['role']);

        $user = Auth::user()->load('roles');

        if (! $this->portalAccessService->canAccessPortal($user, $validated['role'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['role' => 'This account is not registered for the selected portal.'])
                ->onlyInput('username', 'role');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Sign the user out of the portal session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Map demo IDs / phones to seeded emails so the login UI stays familiar.
     */
    private function resolveLoginEmail(string $username): string
    {
        $aliases = [
            'PIA-2026-8812' => 'student@lms.test',
            'student@lms.test' => 'student@lms.test',
            '03001234567' => 'parent@lms.test',
            '0300-1234567' => 'parent@lms.test',
            'parent@lms.test' => 'parent@lms.test',
            'FAC-BIO-441' => 'teacher@lms.test',
            'teacher@lms.test' => 'teacher@lms.test',
            'accounts@pinnacle.edu.pk' => 'accounts@lms.test',
            'accounts@lms.test' => 'accounts@lms.test',
            'admin@lms.test' => 'admin@lms.test',
            'bursar@pinnacle.edu.pk' => 'accounts@lms.test',
        ];

        return $aliases[$username] ?? $username;
    }

    /**
     * Handle Inquiry Submissions
     */
    public function submitInquiry(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:25',
            'grade' => 'nullable|string|max:50',
            'message' => 'required|string|max:1000',
        ]);

        return back()->with('success', 'Thank you! Your inquiry has been submitted. Our admission counselor will reach out shortly.');
    }
}
