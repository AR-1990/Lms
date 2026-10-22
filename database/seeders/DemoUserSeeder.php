<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo staff and parent logins (demo1@lms.test … demo12@lms.test, password "password")
 * so lists have rows to edit and delete during development. Not part of DatabaseSeeder:
 * run `php artisan db:seed --class=DemoUserSeeder` on a dev database.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['teacher', 'parent', 'accounts'];

        foreach (range(1, 12) as $number) {
            $user = User::where('email', "demo{$number}@lms.test")->first()
                ?? User::factory()->create(['email' => "demo{$number}@lms.test", 'username' => "demo{$number}"]);

            $user->syncRoles([$roles[$number % count($roles)]]);
        }
    }
}
