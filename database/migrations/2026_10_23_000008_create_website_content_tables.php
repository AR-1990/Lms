<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public website content managed from the admin panel: headline stats, testimonials,
 * news & notices (also the home-page ticker) and FAQs. Nothing on the site is hard-coded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_stats', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->unsignedInteger('value');
            $table->string('suffix', 10)->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('website_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('role_title', 150)->nullable();
            $table->text('quote');
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('website_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('title');
            $table->string('slug')->index();
            $table->string('excerpt', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->date('published_on')->nullable()->index();
            $table->unsignedTinyInteger('read_minutes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_in_ticker')->default(false)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('website_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        if (! DB::table('permissions')->where('slug', 'manage-website')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Manage Website', 'slug' => 'manage-website', 'group' => 'administration',
                'description' => 'Public website stats, testimonials, news & notices and FAQs.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-website')->value('id')],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-website')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        Schema::dropIfExists('website_faqs');
        Schema::dropIfExists('website_posts');
        Schema::dropIfExists('website_testimonials');
        Schema::dropIfExists('website_stats');
    }
};
