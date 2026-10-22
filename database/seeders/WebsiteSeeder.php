<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\WebsiteFaq;
use App\Models\WebsitePost;
use App\Models\WebsiteStat;
use App\Models\WebsiteTestimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starting content for the public website; everything here is edited later under Admin → Website.
 */
class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Enrolled Students', 2500, '+', 'graduation-cap'],
            ['Matric / O-Level Pass Rate', 100, '%', 'award'],
            ['Certified Educators', 120, '+', 'users'],
            ['Years of Academic Legacy', 28, '+', 'building'],
        ] as $position => [$label, $value, $suffix, $icon]) {
            WebsiteStat::updateOrCreate(['label' => $label], ['value' => $value, 'suffix' => $suffix, 'icon' => $icon, 'sort_order' => $position, 'is_active' => true]);
        }

        foreach ([
            ['Dr. Farhan Qureshi', 'Parent of Grade 8 & 10 Students', 'Pinnacle Academy has provided the best academic balance for both of my children. Their STEM program and focus on ethical character building have truly helped them thrive.'],
            ['Ayesha Mansoor', 'Parent of Montessori Student', 'The warmth of the early years teachers and the safe, caring environment gave our daughter the smoothest start to school life. Highly recommend to every parent!'],
            ['Bilal Tariq', 'Alumni (Class of 2023 - Now at LUMS)', 'The conceptual foundation in sciences and public speaking mentorship at Pinnacle prepared me exceptionally well for university life and competitive exams.'],
        ] as $position => [$name, $role, $quote]) {
            WebsiteTestimonial::updateOrCreate(['name' => $name], ['role_title' => $role, 'quote' => $quote, 'sort_order' => $position, 'is_active' => true]);
        }

        $category = fn (string $name): ?int => Lookup::where('type', 'news_category')->where('name', $name)->value('id');

        foreach ([
            ['Achievements', 'Pinnacle Robotics Team Triumphs at National STEM Olympiad 2026', 'Our student engineering squad secured first place with their autonomous smart agricultural rover prototype among 60 participating institutions.', 'images/hero-robotics.jpg', '2026-08-24', 3, true, true],
            ['Sports', 'Annual Inter-School Athletics Championship & Sports Gala 2026', 'Over 400 student athletes competed across track, football, and basketball tournaments in our 5-acre sports complex.', 'images/sports-gala.jpg', '2026-08-18', 4, false, false],
            ['Academics', '100% A+ & A Grades Secured in Cambridge & Federal Board Examinations', 'Pinnacle students once again proved their academic brilliance with historic merit positions across science and computer streams.', 'images/hero-leadership.jpg', '2026-08-10', 2, false, false],
            ['Campus Life', 'Expansion of The Learning Commons & 4K Smart Classroom Suites', 'Academy inaugurates new interactive digital touchscreens and over 5,000 international scientific literature additions to the central library.', 'images/facility-library.jpg', '2026-07-28', 3, false, false],
            ['Early Years', 'Montessori Sensorial Discovery Week & Creative Exhibition', 'Early years children showcased practical life experiments, phonics spelling bees, and colorful artistic models for visiting parents.', 'images/montessori-kids.jpg', '2026-07-15', 2, false, false],
            ['Admissions', 'Admissions Open for Session 2026-27 & Merit Scholarship Tests', 'Online registration is now active for Playgroup through Grade 11 with up to 100% merit scholarship slots available.', 'images/admissions-meeting.jpg', '2026-07-01', 3, false, true],
            ['Notice', 'Parent-Teacher Meeting for Mid-Term assessments is scheduled for next Saturday', null, null, '2026-10-15', null, false, true],
        ] as [$categoryName, $title, $excerpt, $image, $date, $minutes, $featured, $ticker]) {
            WebsitePost::updateOrCreate(['slug' => Str::slug($title)], [
                'category_id' => $category($categoryName),
                'title' => $title,
                'excerpt' => $excerpt,
                'image_path' => $image,
                'published_on' => $date,
                'read_minutes' => $minutes,
                'is_featured' => $featured,
                'show_in_ticker' => $ticker,
                'is_published' => true,
            ]);
        }

        foreach ([
            ['What is the minimum age requirement for Montessori admission?', 'Children should be at least 2.5 to 3 years old by the start of the academic session for Playgroup, and 3.5 to 4 years old for Nursery.'],
            ['Can students transfer between Federal Board (Matric) and Cambridge (O-Levels)?', "Yes. At Grade 8, our academic counselors evaluate each student's aptitude and provide structured bridging modules for a smooth move into either the Cambridge O-Levels or Federal Board Matric track."],
            ['Are sibling discounts automatically applied?', 'Yes. The sibling concession on monthly tuition is applied to the second and later enrolled siblings once their admission records are verified.'],
            ['What safety measures are implemented for school transport?', 'All school buses are air-conditioned, tracked by GPS with SMS alerts for parents, and accompanied by female attendant staff for early years and primary students.'],
        ] as $position => [$question, $answer]) {
            WebsiteFaq::updateOrCreate(['question' => $question], ['answer' => $answer, 'sort_order' => $position, 'is_active' => true]);
        }
    }
}
