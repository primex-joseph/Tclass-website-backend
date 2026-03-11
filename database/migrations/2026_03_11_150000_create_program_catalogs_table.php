<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_catalogs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['certificate', 'diploma']);
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 100);
            $table->text('description');
            $table->string('duration', 100);
            $table->string('credential_label', 100);
            $table->string('badge_text', 100);
            $table->string('icon_key', 50);
            $table->string('theme_key', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('program_catalogs')->insert([
            [
                'type' => 'certificate',
                'title' => 'Rigid Highway Dump Truck NCII',
                'slug' => 'rigid-highway-dump-truck-ncii',
                'category' => 'heavy-equipment',
                'description' => 'School-based heavy equipment training under scholarship support.',
                'duration' => '3 months',
                'credential_label' => 'NCII',
                'badge_text' => 'Limited slots',
                'icon_key' => 'truck',
                'theme_key' => 'orange',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'certificate',
                'title' => 'Transit Mixer NCII',
                'slug' => 'transit-mixer-ncii',
                'category' => 'heavy-equipment',
                'description' => 'Concrete mixer operation training for rapid field readiness.',
                'duration' => '3 months',
                'credential_label' => 'NCII',
                'badge_text' => 'Now accepting',
                'icon_key' => 'truck',
                'theme_key' => 'orange',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'certificate',
                'title' => 'Forklift NCII',
                'slug' => 'forklift-ncii',
                'category' => 'heavy-equipment',
                'description' => 'Practical forklift operation with competency assessments.',
                'duration' => '2 months',
                'credential_label' => 'NCII',
                'badge_text' => 'Open enrollment',
                'icon_key' => 'hard-hat',
                'theme_key' => 'orange',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'certificate',
                'title' => '3-Year Diploma in ICT',
                'slug' => '3-year-diploma-in-ict',
                'category' => 'ict',
                'description' => 'Full diploma pathway for digital and IT roles.',
                'duration' => '3 years',
                'credential_label' => 'NCII',
                'badge_text' => '5 slots left',
                'icon_key' => 'laptop',
                'theme_key' => 'purple',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'certificate',
                'title' => 'Housekeeping NCII',
                'slug' => 'housekeeping-ncii',
                'category' => 'services',
                'description' => 'Hospitality-focused housekeeping training track.',
                'duration' => '2 months',
                'credential_label' => 'NCII',
                'badge_text' => 'Now open',
                'icon_key' => 'award',
                'theme_key' => 'green',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'certificate',
                'title' => 'Health Care Services NCII',
                'slug' => 'health-care-services-ncii',
                'category' => 'services',
                'description' => 'Caregiver-assistant program aligned to TESDA standards.',
                'duration' => '6 months',
                'credential_label' => 'NCII',
                'badge_text' => 'Limited slots',
                'icon_key' => 'users',
                'theme_key' => 'green',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'diploma',
                'title' => 'Bachelor of Science in Information Technology',
                'slug' => 'bachelor-of-science-in-information-technology',
                'category' => 'ict',
                'description' => '4-year degree focused on software, systems, and database fundamentals.',
                'duration' => '4 years',
                'credential_label' => 'Degree',
                'badge_text' => 'Open enrollment',
                'icon_key' => 'laptop',
                'theme_key' => 'purple',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'diploma',
                'title' => 'Bachelor of Science in Computer Science',
                'slug' => 'bachelor-of-science-in-computer-science',
                'category' => 'ict',
                'description' => '4-year computing program with programming, algorithms, and systems design.',
                'duration' => '4 years',
                'credential_label' => 'Degree',
                'badge_text' => 'Now accepting',
                'icon_key' => 'laptop',
                'theme_key' => 'purple',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'diploma',
                'title' => 'Bachelor of Technical Vocational Teacher Education',
                'slug' => 'bachelor-of-technical-vocational-teacher-education',
                'category' => 'education',
                'description' => '4-year teaching degree for technical-vocational and skills-based instruction.',
                'duration' => '4 years',
                'credential_label' => 'Degree',
                'badge_text' => 'Open enrollment',
                'icon_key' => 'graduation-cap',
                'theme_key' => 'blue',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'diploma',
                'title' => 'Bachelor of Elementary Education',
                'slug' => 'bachelor-of-elementary-education',
                'category' => 'education',
                'description' => '4-year degree preparing future elementary educators and classroom practitioners.',
                'duration' => '4 years',
                'credential_label' => 'Degree',
                'badge_text' => 'Limited slots',
                'icon_key' => 'users',
                'theme_key' => 'blue',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'diploma',
                'title' => 'Bachelor of Science in Business Administration',
                'slug' => 'bachelor-of-science-in-business-administration',
                'category' => 'business',
                'description' => '4-year program covering management, operations, and entrepreneurship.',
                'duration' => '4 years',
                'credential_label' => 'Degree',
                'badge_text' => 'Now accepting',
                'icon_key' => 'briefcase',
                'theme_key' => 'green',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'diploma',
                'title' => 'Bachelor of Science in Hospitality Management',
                'slug' => 'bachelor-of-science-in-hospitality-management',
                'category' => 'business',
                'description' => '4-year hospitality track for tourism, service operations, and hotel management.',
                'duration' => '4 years',
                'credential_label' => 'Degree',
                'badge_text' => 'Open enrollment',
                'icon_key' => 'award',
                'theme_key' => 'green',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('program_catalogs');
    }
};
