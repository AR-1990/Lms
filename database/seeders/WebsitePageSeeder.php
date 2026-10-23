<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsiteSectionItem;
use Illuminate\Database\Seeder;

/**
 * The text, pictures and cards of every public page (edited later under Admin → Website → Pages), plus the
 * school's contact details in system settings. Adds only what is missing, so the admin's edits are kept.
 * Titles mark coloured words with [brackets]; links are site paths.
 */
class WebsitePageSeeder extends Seeder
{
    public function run(): void
    {
        $settings = SystemSetting::query()->first();

        if ($settings !== null) {
            foreach ([
                'school_tagline' => 'International Academy',
                'admissions_phone' => '+92 300 1234567',
                'whatsapp_number' => '923001234567',
                'contact_email' => 'info@pinnacle.edu.pk',
                'admissions_email' => 'admissions@pinnacle.edu.pk',
                'office_hours' => 'Mon - Fri: 8:00 AM - 3:30 PM',
                'visiting_hours' => "Monday - Friday: 08:00 AM to 03:30 PM\nSaturday: 09:00 AM to 01:30 PM (Admissions Desk)",
                'footer_text' => 'A premier educational institution dedicated to intellectual curiosity, character building, STEM robotics excellence, and global leadership since 1998.',
            ] as $field => $value) {
                $settings->{$field} ??= $value;
            }

            $settings->save();
        }

        foreach ($this->pages() as $slug => [$page, $sections]) {
            $record = WebsitePage::firstOrCreate(['slug' => $slug], $page + ['is_published' => true]);

            foreach (array_values($sections) as $position => [$key, $fields, $items]) {
                if ($record->sections()->where('section_key', $key)->exists()) {
                    continue;
                }

                $section = $record->sections()->create(['section_key' => $key, 'sort_order' => $position, 'is_active' => true] + $fields);
                $this->items($section, $items);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items  each may carry 'children'
     */
    private function items(WebsiteSection $section, array $items, ?int $parentId = null): void
    {
        foreach (array_values($items) as $position => $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);

            $created = WebsiteSectionItem::create($item + ['website_section_id' => $section->id, 'parent_id' => $parentId, 'sort_order' => $position, 'is_active' => true]);
            $this->items($section, $children, $created->id);
        }
    }

    /**
     * slug => [page fields, [[section key, section fields, items], …]]
     *
     * @return array<string, array{0: array<string, mixed>, 1: array<int, array{0: string, 1: array<string, mixed>, 2: array<int, array<string, mixed>>}>}>
     */
    private function pages(): array
    {
        $facility = fn (string $badge, string $tone, string $image, string $title, string $body, string $label, string $url): array => compact('badge', 'tone', 'title', 'body') + ['image_path' => $image, 'link_label' => $label, 'link_url' => $url];
        $features = fn (string ...$titles): array => array_map(fn (string $title): array => ['title' => $title], $titles);

        return [
            'site' => [['name' => 'Shared blocks (footer, call to action)'], [
                ['cta', ['badge' => 'Admissions Open Session 2026-27', 'title' => 'Give Your Child The Foundation For A Bright Future', 'description' => 'Join our vibrant community of innovative thinkers, achievers, and leaders. Limited merit-based seats available for Montessori to Grade 11.', 'cta_label' => 'Apply For Admission', 'cta_url' => '/admissions', 'cta2_label' => 'Book Campus Tour', 'cta2_url' => '/contact'], []],
                ['social', ['title' => 'Social links'], [
                    ['title' => 'Facebook', 'icon' => 'facebook', 'link_url' => '#'],
                    ['title' => 'Instagram', 'icon' => 'instagram', 'link_url' => '#'],
                    ['title' => 'YouTube', 'icon' => 'youtube', 'link_url' => '#'],
                    ['title' => 'LinkedIn', 'icon' => 'linkedin', 'link_url' => '#'],
                ]],
                ['footer_links_1', ['title' => 'Academic Wings'], [
                    ['title' => 'Early Years & Montessori', 'link_url' => '/academics#early-years'],
                    ['title' => 'Primary School (Grades 1-5)', 'link_url' => '/academics#primary-wing'],
                    ['title' => 'Middle Wing (Grades 6-8)', 'link_url' => '/academics#middle-wing'],
                    ['title' => 'Cambridge O-Levels', 'link_url' => '/academics#matric-o-levels'],
                    ['title' => 'Federal Board (Matric / HSSC)', 'link_url' => '/academics#matric-o-levels'],
                    ['title' => 'STEM & Robotics Academy', 'link_url' => '/academics#stem-program'],
                ]],
                ['footer_links_2', ['title' => 'Explore & Portals'], [
                    ['title' => 'About Academy', 'link_url' => '/about'],
                    ['title' => 'Admissions & Fee Guide', 'link_url' => '/admissions'],
                    ['title' => 'Campus Infrastructure', 'link_url' => '/campus'],
                    ['title' => 'News & Event Gallery', 'link_url' => '/news'],
                    ['title' => 'Unified Portals Gateway', 'link_url' => '/portal'],
                    ['title' => 'Portal Sign In →', 'link_url' => '/login', 'tone' => 'amber'],
                ]],
                ['footer_bottom', ['title' => 'Bottom links'], [
                    ['title' => 'Privacy Policy', 'link_url' => '/contact'],
                    ['title' => 'Terms of Admission', 'link_url' => '/contact'],
                    ['title' => 'Portal Login', 'link_url' => '/login'],
                ]],
            ]],

            'home' => [[
                'name' => 'Home', 'meta_title' => 'Pinnacle International Academy | Inspiring Global Leaders of Tomorrow',
                'meta_description' => 'Discover Pinnacle International Academy. World-class STEM & robotics labs, Cambridge & BISE academic streams, athletic facilities, and holistic character building.',
            ], [
                ['hero_slides', ['title' => 'Home slider'], [
                    [
                        'badge' => 'Session 2026-27 • Merit Admissions Open', 'title' => 'Inspiring [Global Leaders] & Visionary Minds',
                        'body' => 'Cultivating intellectual curiosity, ethical character, and 21st-century technological mastery. Where young minds discover their true potential and rise to lead the world.',
                        'image_path' => 'images/hero-leadership.jpg', 'link_label' => 'Apply For Admission', 'link_url' => '/admissions', 'link2_label' => 'Explore Curriculum', 'link2_url' => '/academics',
                        'subtitle' => 'Academic Excellence', 'value' => 'Est. 1998',
                        'children' => [
                            ['title' => '100% Scholarships', 'body' => 'Awarded to top board position holders', 'icon' => 'dollar', 'tone' => 'blue'],
                            ['title' => 'Cambridge & BISE', 'body' => 'Dual international & national pathways', 'icon' => 'graduation', 'tone' => 'amber'],
                            ['title' => 'Safe Smart Campus', 'body' => '24/7 Monitored CCTV & RFID buses', 'icon' => 'shield', 'tone' => 'emerald'],
                        ],
                    ],
                    [
                        'badge' => 'STEM & Future Technologies Hub', 'tone' => 'blue', 'title' => 'Empowering Innovators with [Robotics & AI]',
                        'body' => 'From Arduino circuitry and 3D prototyping to Python coding, our students build real-world technological solutions that win national championships.',
                        'image_path' => 'images/hero-robotics.jpg', 'link_label' => 'Explore STEM Labs', 'link_url' => '/academics#stem-program', 'link2_label' => 'View Innovation Studio', 'link2_url' => '/campus',
                        'subtitle' => 'National Olympiad Champions',
                        'children' => [
                            ['title' => 'Hands-on 21st Century Tech', 'body' => 'Every student from Grade 3 onwards undergoes weekly practical lab projects in coding, mechanics, and design thinking.', 'icon' => 'code', 'tone' => 'blue'],
                            ['title' => '150+ Working Prototypes Built Yearly', 'body' => 'Showcased annually at the Pinnacle National Science & Tech Fair.', 'icon' => 'sparkles', 'tone' => 'amber'],
                        ],
                    ],
                    [
                        'badge' => 'Holistic Development & Athletics', 'tone' => 'amber', 'title' => 'Championing [Sports, Arts & Values]',
                        'body' => 'Spacious 5-acre sports arena, turf pitch, cricket nets, fine arts studios, and theatrical debate societies nurturing well-rounded champions.',
                        'image_path' => 'images/hero-campus.jpg', 'link_label' => 'Campus Facilities', 'link_url' => '/campus', 'link2_label' => 'Book a Visit', 'link2_url' => '/contact',
                        'subtitle' => '5-Acre Athletic Arena',
                        'children' => [
                            ['title' => '8+ Sports Disciplines', 'body' => 'Professional coaching staff', 'icon' => 'compass', 'tone' => 'amber'],
                            ['title' => 'Model UN & Debate Club', 'body' => 'Oratory & leadership training', 'icon' => 'users', 'tone' => 'blue'],
                        ],
                    ],
                ]],
                ['facilities', ['subtitle' => 'World-Class Environment', 'title' => 'Explore Our Modern Campus Facilities', 'description' => 'Designed with natural light, ergonomic warmth, and cutting-edge educational tools.'], [
                    $facility('Interactive Tech', 'amber', 'images/facility-smart-class.jpg', 'Interactive Smart Classrooms', 'Equipped with 75" 4K interactive digital screens, climate control, and ergonomic collaborative seating.', 'Explore Classroom', '/campus'),
                    $facility('Research Suites', 'blue', 'images/facility-science-lab.jpg', 'Modern Science Laboratories', 'Safety-certified chemistry, physics, and biology lab workstations with digital microscopes and glassware.', 'Explore Labs', '/campus'),
                    $facility('15,000+ Titles', 'amber', 'images/facility-library.jpg', 'Digital Learning Commons', 'Spacious library sanctuary with global digital databases, study cubicles, and quiet reading areas.', 'Explore Library', '/campus'),
                ]],
                ['wings', ['subtitle' => 'Academic Wings', 'title' => 'A Prestigious Educational Continuum', 'description' => 'From playful early sensory exploration to rigorous pre-university college preparation, our progressive structure ensures every child excels.'], [
                    ['badge' => 'Ages 3 - 5', 'tone' => 'amber', 'image_path' => 'images/montessori-kids.jpg', 'title' => 'Early Years & Montessori', 'body' => 'Sensory discovery, Jolly Phonics immersion, early numeracy, and social-emotional confidence building.', 'value' => 'Playgroup • Nursery • Prep', 'link_label' => 'Explore Wing', 'link_url' => '/academics#early-years', 'children' => $features('Dedicated Montessori practical life apparatus', 'Phonetics, motor skills & indoor soft gym')],
                    ['badge' => 'Grades 1 - 8', 'tone' => 'blue', 'image_path' => 'images/facility-smart-class.jpg', 'title' => 'Primary & Middle School', 'body' => 'Singapore Math, Oxford Science curriculum, critical thinking, bilingual fluency, and coding foundations.', 'value' => 'Grades 1 through 8', 'link_label' => 'Explore Wing', 'link_url' => '/academics#primary-wing', 'children' => $features('STEM integration & laboratory research', 'Public speaking & debate society')],
                    ['badge' => 'Grades 9 - 12', 'tone' => 'dark', 'image_path' => 'images/hero-leadership.jpg', 'title' => 'Matric & Cambridge O-Levels', 'body' => 'High-performance academic streams with test-series bootcamps, university counseling, and career preparation.', 'value' => 'Matric • O-Levels • HSSC', 'link_label' => 'Explore Wing', 'link_url' => '/academics#matric-o-levels', 'children' => $features('Pre-Medical, Pre-Engineering & Computer Science', '100% Top grades & university admissions')],
                ]],
                ['fee_estimator', ['subtitle' => 'Instant Fee Calculator', 'title' => 'Transparent Tuition Estimator', 'description' => "Pick a class to see this session's fee, with concessions and optional services such as transport."], []],
                ['inquiry', ['badge' => 'Fast Response', 'title' => 'Request Admission Prospectus', 'description' => "Fill out the form below to lock your child's priority registration."], []],
                ['testimonials', ['subtitle' => 'Community Endorsements', 'title' => 'Trusted by Generations of Families', 'description' => 'Read how Pinnacle Academy empowers students and satisfies parents.'], []],
                ['events', ['subtitle' => 'Campus Life', 'title' => 'Upcoming Activities & Events', 'description' => 'Key milestones and community gatherings on our calendar.'], []],
                ['whatsapp', ['title' => 'Admissions Helpline'], []],
            ]],

            'about' => [[
                'name' => 'About Us', 'meta_title' => 'About Our Academy | Pinnacle International Academy',
                'meta_description' => 'Discover Pinnacle Academy’s 28-year legacy, leadership, vision, and mission of academic excellence and character development.',
                'hero_pill' => '28 Years of Academic Excellence', 'hero_title' => 'Nurturing Visionaries, [Scholars & Leaders]',
                'hero_text' => 'Founded in 1998, Pinnacle International Academy stands as a benchmark of holistic education, combining rigorous Cambridge & National curricula with state-of-the-art STEM laboratories.',
                'hero_image_path' => 'images/hero-campus.jpg',
            ], [
                ['principal', [
                    'subtitle' => 'Leadership Perspective', 'title' => 'A Message From The Principal',
                    'description' => '"At Pinnacle, education extends far beyond memorization and textbooks. We cultivate dynamic thinkers who ask probing questions, embrace ethical leadership, and create positive impact in an interconnected global community."',
                    'body' => 'Our faculty is dedicated to fostering curiosity through project-based STEM investigations, sportsmanship across our 5-acre sports facilities, and personal character development that equips young minds for university triumph.',
                    'image_path' => 'images/principal-portrait.jpg', 'image_caption' => 'Dr. Richard Sterling', 'image_note' => 'Principal & Academic Director',
                    'cta_label' => 'Join Our Community', 'cta_url' => '/admissions', 'cta2_label' => 'Explore Campus Life', 'cta2_url' => '/campus',
                ], []],
                ['philosophy', ['subtitle' => 'Guiding Philosophy', 'title' => 'Our Mission, Vision & Core Values', 'description' => 'The enduring principles that define our educational philosophy and shape our students into global changemakers.'], [
                    ['icon' => 'compass', 'tone' => 'orange', 'title' => 'Our Vision', 'body' => 'To be the leading center of academic and ethical leadership, producing confident, compassionate global citizens capable of navigating future frontiers in technology and society.'],
                    ['icon' => 'dollar', 'tone' => 'blue', 'title' => 'Our Mission', 'body' => 'To provide a challenging, supportive, and technology-rich learning environment that fosters critical thinking, intellectual curiosity, artistic expression, and moral integrity.'],
                    ['icon' => 'graduation', 'tone' => 'emerald', 'title' => '28-Year Legacy', 'body' => 'Over two and a half decades of consistent 100% board positions, national robotics Olympiad victories, and alumni flourishing in world-renowned universities.'],
                ]],
            ]],

            'academics' => [[
                'name' => 'Academics', 'meta_title' => 'Academic Curriculum & Wings | Pinnacle International Academy',
                'meta_description' => 'Explore Montessori, Primary, Middle, Cambridge O-Levels, and Federal Board streams at Pinnacle Academy with integrated STEM & robotics.',
                'hero_pill' => 'Curriculum & Pedagogy', 'hero_title' => 'Intellectual Rigor & [Future-Ready Skills]',
                'hero_text' => 'Our dual-stream curriculum merges Cambridge International assessment standards with the national Federal Board framework, enriched with hands-on STEM robotics and arts.',
                'hero_image_path' => 'images/facility-science-lab.jpg',
            ], [
                ['wings', ['subtitle' => 'Academic Continuum', 'title' => 'Structured for Every Developmental Stage', 'description' => 'Click through our academic wings to explore tailored curricula, learning methodologies, and student milestones.'], [
                    ['subtitle' => 'Early Years & Montessori', 'value' => 'early-years', 'badge' => 'Ages 3 to 5 Years', 'tone' => 'amber', 'title' => 'Early Years & Sensory Montessori', 'body' => 'Our Montessori environment inspires spontaneous inquiry and joyful discovery. Certified teachers use multisensory apparatus to nurture phonological awareness, early numeracy, and fine motor skills.', 'image_path' => 'images/montessori-kids.jpg', 'link_label' => 'Enroll in Early Years', 'link_url' => '/admissions', 'children' => [
                        ['title' => 'Jolly Phonics & Dual Fluency:', 'body' => 'Systematic phonemic reading and bilingual conversation.'],
                        ['title' => 'Sensorial & Practical Life:', 'body' => 'Cultivating spatial reasoning, focus, and social independence.'],
                    ]],
                    ['subtitle' => 'Primary Wing (Grades 1-5)', 'value' => 'primary-wing', 'badge' => 'Grades 1 to 5', 'tone' => 'blue', 'title' => 'Primary Wing Curriculum', 'body' => 'Building a resilient foundation in analytical mathematics, inquiry-based scientific investigations, and expressive English writing through modern Oxford & Cambridge primary frameworks.', 'image_path' => 'images/facility-smart-class.jpg', 'link_label' => 'Apply For Primary Wing', 'link_url' => '/admissions', 'children' => [
                        ['title' => 'Singapore Math Conceptual Model:', 'body' => 'Focus on problem-solving and mental arithmetic.'],
                        ['title' => 'Weekly STEM Studio:', 'body' => 'Introductory robotics, Scratch visual coding, and science experiments.'],
                    ]],
                    ['subtitle' => 'Middle School (Grades 6-8)', 'value' => 'middle-wing', 'badge' => 'Grades 6 to 8', 'tone' => 'amber', 'title' => 'Middle School Transition', 'body' => 'The transitional bridge where students master advanced sciences, coding in Python, public debate, and exploratory research to choose between O-Levels and Matric tracks.', 'image_path' => 'images/facility-library.jpg', 'link_label' => 'Apply For Middle School', 'link_url' => '/admissions', 'children' => [
                        ['title' => 'Dedicated Physics, Chem & Bio Labs:', 'body' => 'Hands-on weekly laboratory sessions.'],
                        ['title' => 'Model UN & Oratory Society:', 'body' => 'Cultivating public speaking and diplomacy.'],
                    ]],
                    ['subtitle' => 'Cambridge & Matric (9-12)', 'value' => 'matric-o-levels', 'badge' => 'Grades 9 to 12', 'tone' => 'dark', 'title' => 'Cambridge O-Levels & Federal Board', 'body' => 'Intensive pre-university tracks with rigorous mock exam series, individual career counseling, and university placement guidance producing top nationwide results.', 'image_path' => 'images/hero-leadership.jpg', 'link_label' => 'Apply For Senior Wing', 'link_url' => '/admissions', 'children' => [
                        ['title' => 'Pre-Medical, Pre-Engineering & Computer Science:', 'body' => 'State-of-the-art facilities.'],
                        ['title' => 'University Placement Counseling:', 'body' => 'SAT prep, personal statement workshops & scholarships.'],
                    ]],
                ]],
                ['stem', ['subtitle' => 'Future Technologies Hub', 'title' => 'Robotics, AI & Rapid Prototyping', 'description' => 'In an era defined by artificial intelligence, we equip students with real-world technical skills. Our innovation lab features Arduino workstations, 3D printers, Python programming, and sensor integration.', 'image_path' => 'images/hero-robotics.jpg', 'cta_label' => 'Join STEM Program', 'cta_url' => '/admissions'], [
                    ['title' => 'Arduino & Microcontrollers', 'tone' => 'amber'],
                    ['title' => 'Python Coding', 'tone' => 'blue'],
                    ['title' => '3D Rapid Prototyping', 'tone' => 'emerald'],
                    ['title' => 'AI & Automation', 'tone' => 'dark'],
                ]],
            ]],

            'campus' => [[
                'name' => 'Campus Life', 'meta_title' => 'Campus Life & Facilities | Pinnacle International Academy',
                'meta_description' => 'Discover Pinnacle Academy campus life, smart classrooms, digital library, sports arena, STEM labs, and safe RFID transport network.',
                'hero_pill' => '5-Acre World-Class Campus', 'hero_title' => 'Infrastructure Designed for [Inspiration & Safety]',
                'hero_text' => 'Spread across 5 lush green acres, our campus provides an energetic environment equipped with interactive classrooms, multi-sports complexes, and certified STEM laboratories.',
                'hero_image_path' => 'images/hero-campus.jpg',
            ], [
                ['facilities', ['subtitle' => 'Campus Amenities', 'title' => 'Everything a Student Needs to Flourish', 'description' => 'We invest in premium infrastructure to ensure your child experiences learning in comfortable, stimulating surroundings.'], [
                    $facility('Interactive Tech', 'amber', 'images/facility-smart-class.jpg', 'Interactive Smart Classrooms', '75" 4K touch interactive flat panels, climate control, ergonomic modular seating, and high-speed fiber connectivity.', 'Schedule a Visit', '/contact'),
                    $facility('Safety-Certified', 'blue', 'images/facility-science-lab.jpg', 'Modern Science Laboratories', 'Safety-certified physics, chemistry, and biology suites with digital microscopes and chemical fume hoods.', 'Schedule a Visit', '/contact'),
                    $facility('15,000+ Volumes', 'amber', 'images/facility-library.jpg', 'The Learning Commons & Library', 'A sanctuary with over 15,000 physical volumes, international digital research databases, and quiet study pods.', 'Schedule a Visit', '/contact'),
                    $facility('5-Acre Turf', 'blue', 'images/sports-gala.jpg', 'Athletic Arena & Sports Turf', 'Turf football pitch, synthetic basketball court, indoor badminton arena, table tennis hall, and athletic coaching.', 'Schedule a Visit', '/contact'),
                    $facility('Maker Studio', 'amber', 'images/hero-robotics.jpg', 'STEM & Robotics Innovation Studio', 'Arduino prototyping, 3D printers, electronics workbenches, and coding stations for Olympiad preparation.', 'Schedule a Visit', '/contact'),
                    $facility('Early Years', 'emerald', 'images/montessori-kids.jpg', 'Montessori Discovery Play Zone', 'Padded soft play apparatus, sensory exploration activity tables, and storytelling amphitheater.', 'Schedule a Visit', '/contact'),
                ]],
                ['safety', ['subtitle' => 'Student Safety First', 'title' => 'Uncompromised Security & Wellbeing', 'description' => 'We treat campus safety with the highest gravity. Our comprehensive multi-layered security infrastructure ensures your peace of mind while your child is in our care.'], [
                    ['icon' => 'shield', 'tone' => 'blue', 'title' => '24/7 Monitored CCTV Coverage', 'body' => 'Over 120 high-definition security cameras covering boundary perimeters, corridors, and gates.'],
                    ['icon' => 'heart', 'tone' => 'amber', 'title' => 'Full-Time Medical Bay & Nursing Staff', 'body' => 'Equipped infirmary with certified nursing staff for first aid, health screening, and emergency triage.'],
                    ['icon' => 'bus', 'tone' => 'emerald', 'title' => 'Live GPS & RFID School Transport', 'body' => 'Air-conditioned school buses with female attendants and real-time tracking notifications for parents.'],
                ]],
                ['tour', ['badge' => 'Experience Pinnacle', 'title' => 'Experience Our Campus Firsthand', 'description' => 'We invite parents and prospective students to take a guided personalized tour of our smart classrooms, science suites, and sports grounds.', 'cta_label' => 'Book a Personalized Campus Tour', 'cta_url' => '/contact'], []],
            ]],

            'admissions' => [[
                'name' => 'Admissions', 'meta_title' => 'Admissions & Fee Guide | Pinnacle International Academy',
                'meta_description' => 'Apply online for admissions at Pinnacle International Academy. Explore our admission roadmap, transparent fee estimator, and criteria.',
                'hero_pill' => 'Session 2026-27 • Applications Open', 'hero_title' => "Begin Your Child's Journey of [Excellence]",
                'hero_text' => 'We welcome curious, ambitious students into our vibrant academic family. Explore our simple 4-step roadmap, transparent fee guide, and submit your registration online.',
                'hero_image_path' => 'images/admissions-meeting.jpg',
            ], [
                ['steps', ['subtitle' => 'Streamlined Process', 'title' => '4 Simple Steps to Enrollment', 'description' => 'Our admissions team provides comprehensive guidance throughout the application lifecycle.'], [
                    ['value' => '01', 'tone' => 'blue', 'title' => 'Online Registration', 'body' => 'Submit the simple admission inquiry form online or visit our campus front desk for a prospectus.'],
                    ['value' => '02', 'tone' => 'amber', 'title' => 'Aptitude Assessment', 'body' => "Age-appropriate diagnostic assessment in English and Math to understand your child's baseline strengths."],
                    ['value' => '03', 'tone' => 'blue', 'title' => 'Parent Interaction', 'body' => 'A warm 20-minute conversation with our academic principal to align on goals and expectations.'],
                    ['value' => '04', 'tone' => 'emerald', 'title' => 'Offer & Welcome', 'body' => 'Issuance of official admission offer, fee settlement, uniform orientation, and LMS credential onboarding.'],
                ]],
                ['counselor', ['subtitle' => 'Dedicated Guidance', 'title' => 'Meet Our Admission Counselors', 'description' => 'Unsure which academic stream (Cambridge O-Levels or Federal Board) is right for your child? Our counselors provide personalized assessments, curriculum comparisons, and campus tour bookings.', 'image_path' => 'images/admissions-meeting.jpg', 'image_caption' => 'Admissions Direct Line'], []],
                ['form', ['badge' => 'Fast Online Enrollment', 'title' => 'Submit Online Application', 'description' => 'Fill out the student details to reserve an assessment interview slot.'], []],
            ]],

            'contact' => [[
                'name' => 'Contact', 'meta_title' => 'Contact & Campus Visit | Pinnacle International Academy',
                'meta_description' => 'Contact Pinnacle International Academy. Get in touch with our admissions office, accounts department, or submit an online inquiry.',
                'hero_pill' => 'Get in Touch • Admissions Helpdesk', 'hero_title' => "We're Here to Guide Your [Child's Future]",
                'hero_text' => 'Have questions about our academic pathways, admission deadlines, fee structures, or campus visits? Our counselors and administration are at your service.',
                'hero_image_path' => 'images/facility-library.jpg',
            ], [
                ['channels', ['subtitle' => 'Direct Communication', 'title' => 'Campus Helplines & Location', 'description' => 'Visit our main campus during working hours or connect with dedicated department counselors.'], []],
                ['form', ['badge' => 'Fast Response', 'title' => 'Send Us a Message', 'description' => 'Fill out the details below and our counseling department will get back to you promptly.'], []],
                ['faq', ['subtitle' => 'Got Questions?', 'title' => 'Frequently Asked Questions', 'description' => 'Quick answers to common questions about admissions, fees, curriculum, and policies.'], []],
            ]],

            'news' => [[
                'name' => 'News & Events', 'meta_title' => 'News, Events & Happenings | Pinnacle International Academy',
                'meta_description' => 'Latest news, sports galas, robotics championships, academic achievements, and community events at Pinnacle International Academy.',
                'hero_pill' => 'Campus Pulse • News & Events', 'hero_title' => 'Happenings, Triumphs & [Campus Life]',
                'hero_text' => 'Stay connected with the latest academic milestones, robotics tournament victories, sports galas, and announcements from Pinnacle International Academy.',
                'hero_image_path' => 'images/sports-gala.jpg',
            ], [
                ['calendar', ['subtitle' => 'Official Schedule', 'title' => 'Upcoming Term Calendar', 'description' => 'Key milestones, exams, sports fixtures, and community parent meetings.'], []],
            ]],

            'login' => [[
                'name' => 'Sign In', 'meta_title' => 'Sign In | Pinnacle International Academy',
                'meta_description' => 'Sign in to the Pinnacle International Academy portal for students, parents and staff.',
                'hero_pill' => 'Secure Portal', 'hero_title' => 'Your School, [One Sign In]',
                'hero_text' => 'Students, parents and staff use the same sign-in. Your account opens your own portal.',
                'hero_image_path' => 'images/facility-library.jpg',
            ], [
                ['highlights', ['title' => 'Sign in to your account', 'description' => 'Enter the ID and password given to you by the school.', 'cta_label' => 'Apply for Admission', 'cta_url' => '/admissions'], [
                    ['icon' => 'graduation', 'title' => 'Students', 'body' => 'Timetable, homework, results and notices.'],
                    ['icon' => 'users', 'title' => 'Parents', 'body' => 'Attendance, fee vouchers and report cards.'],
                    ['icon' => 'list', 'title' => 'Teachers', 'body' => 'Attendance, marks, diary and messages.'],
                    ['icon' => 'dollar', 'title' => 'Office & Accounts', 'body' => 'Fees, payroll and school records.'],
                ]],
            ]],

            'portal' => [[
                'name' => 'Portals', 'meta_title' => 'Learning Management & Accounts Portal | Pinnacle Academy',
                'meta_description' => 'Access Pinnacle Academy digital ecosystem. Student LMS, Parent Fee & Grade Portal, Teacher Gradebook, Accounts, and Administration Gateways.',
                'hero_pill' => 'Pinnacle Cloud Ecosystem • LMS & Accounts', 'hero_title' => 'Unified Learning & [Accounts Gateway]',
                'hero_text' => 'Secure digital portals connecting students, parents, educators, and accountants. Access lesson modules, fee billing, grade cards, and attendance records.',
                'hero_image_path' => 'images/hero-robotics.jpg',
            ], [
                ['gateways', ['subtitle' => 'Select Your Access Gateway', 'title' => 'One Platform, Seamless Digital Experience', 'description' => 'Choose your designated portal below to sign in with your verified institutional credentials.'], [
                    ['icon' => 'graduation', 'tone' => 'blue', 'title' => 'Student LMS Portal', 'body' => 'Access digital video lectures, homework submissions, STEM coding assignments, quiz results, and online class schedules.', 'link_label' => 'Student Sign In →', 'link_url' => '/login'],
                    ['icon' => 'users', 'tone' => 'amber', 'title' => 'Parent & Fee Portal', 'body' => 'Download monthly fee challans, view live 1-Link online fee payments, inspect daily attendance, and message teachers directly.', 'link_label' => 'Parent Login & Billing →', 'link_url' => '/login'],
                    ['icon' => 'list', 'tone' => 'emerald', 'title' => 'Teacher Gradebook', 'body' => 'Upload exam marks, manage lesson plans, mark RFID attendance, post digital homework, and schedule parent conferences.', 'link_label' => 'Faculty Sign In →', 'link_url' => '/login'],
                    ['icon' => 'dollar', 'tone' => 'blue', 'title' => 'Accounts & Bursar', 'body' => 'Institutional payroll, fee reconciliation, discount ledger, vendor invoicing, 1-Link bank deposits, and audit reporting.', 'link_label' => 'Accounts Sign In →', 'link_url' => '/login'],
                    ['icon' => 'shield', 'tone' => 'violet', 'title' => 'Principal & Admin Desk', 'body' => 'High-level analytics, admission approvals, staff payroll clearance, timetable builder, and whole-school notification broadcast.', 'link_label' => 'Administration Sign In →', 'link_url' => '/login'],
                    ['icon' => 'help', 'tone' => 'red', 'title' => 'IT & LMS Helpdesk', 'body' => 'Forgot your student ID or password? Submit an account reset request or consult our portal user manual.', 'link_label' => 'Contact IT Support', 'link_url' => '/contact', 'value' => 'outline'],
                ]],
            ]],
        ];
    }
}
