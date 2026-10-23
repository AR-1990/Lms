<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website pages managed by the admin: each page has a hero and SEO text, fixed content blocks
 * (sections) that follow the page design, and repeating cards inside a block (items, which may hold
 * their own bullet points as child items). School contact details move into system settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('website_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->index();
            $table->string('name', 100);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('hero_pill')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_text')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('website_sections', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('website_page_id')->index();
            $table->string('section_key', 40)->index();
            $table->string('subtitle')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('body')->nullable();
            $table->string('badge')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_caption')->nullable();
            $table->string('image_note')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta2_label')->nullable();
            $table->string('cta2_url')->nullable();
            $audit($table);
        });

        Schema::create('website_section_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('website_section_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('tone', 20)->nullable();
            $table->string('badge')->nullable();
            $table->string('value', 100)->nullable();
            $table->string('image_path')->nullable();
            $table->string('link_label')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link2_label')->nullable();
            $table->string('link2_url')->nullable();
            $audit($table);
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->string('school_tagline')->nullable()->after('school_name');
            $table->string('admissions_phone', 30)->nullable()->after('school_phone');
            $table->string('whatsapp_number', 30)->nullable()->after('admissions_phone');
            $table->string('contact_email')->nullable()->after('whatsapp_number');
            $table->string('admissions_email')->nullable()->after('contact_email');
            $table->string('office_hours')->nullable()->after('admissions_email');
            $table->text('visiting_hours')->nullable()->after('office_hours');
            $table->text('footer_text')->nullable()->after('visiting_hours');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', fn (Blueprint $table) => $table->dropColumn(['school_tagline', 'admissions_phone', 'whatsapp_number', 'contact_email', 'admissions_email', 'office_hours', 'visiting_hours', 'footer_text']));
        Schema::dropIfExists('website_section_items');
        Schema::dropIfExists('website_sections');
        Schema::dropIfExists('website_pages');
    }
};
