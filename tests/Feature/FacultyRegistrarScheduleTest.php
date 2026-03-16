<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FacultyWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FacultyRegistrarScheduleTest extends TestCase
{
    private User $registrarUser;

    private User $instructorUser;

    private User $studentA;

    private User $studentB;

    private int $periodId;

    private int $courseOneId;

    private int $offeringOneId;

    private int $offeringTwoId;

    private int $teacherOneId;

    private int $teacherTwoId;

    private int $roomOneId;

    private int $sectionOneId;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTestConnection();

        $this->createSchema();
        $this->resetTables();
        $this->seedPermissions();
        $this->seedFixtureData();
    }

    public function test_registrar_can_update_schedule_and_manage_roster_but_non_registrar_is_forbidden(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $this->patchJson("/api/faculty/class-schedules/{$this->offeringOneId}", [
            'day_of_week' => 'Tue',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])->assertStatus(403);

        $this->postJson("/api/faculty/class-schedules/{$this->offeringOneId}/students", [
            'student_user_id' => $this->studentA->id,
        ])->assertStatus(403);

        Sanctum::actingAs($this->registrarUser);

        $this->getJson("/api/faculty/class-schedules?period_id={$this->periodId}")
            ->assertOk()
            ->assertJsonCount(2, 'items');

        $this->patchJson("/api/faculty/class-schedules/{$this->offeringOneId}", [
            'day_of_week' => 'Tue',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room_id' => $this->roomOneId,
            'teacher_id' => $this->teacherOneId,
            'section_id' => $this->sectionOneId,
        ])->assertOk();

        $this->assertDatabaseHas('class_offerings', [
            'id' => $this->offeringOneId,
            'day_of_week' => 'Tue',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
    }

    public function test_schedule_update_rejects_overlapping_conflicts(): void
    {
        Sanctum::actingAs($this->registrarUser);

        $response = $this->patchJson("/api/faculty/class-schedules/{$this->offeringTwoId}", [
            'day_of_week' => 'Mon',
            'start_time' => '08:30',
            'end_time' => '09:30',
            'room_id' => $this->roomOneId,
            'teacher_id' => $this->teacherTwoId,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Schedule conflict detected.');
        $this->assertNotEmpty((array) $response->json('errors.conflict'));
    }

    public function test_add_student_sets_unofficial_and_writes_audit_log_with_capacity_guard(): void
    {
        DB::table('class_offerings')->where('id', $this->offeringOneId)->update(['capacity' => 1]);

        $existingEnrollmentId = (int) DB::table('enrollments')->insertGetId([
            'user_id' => $this->studentA->id,
            'course_id' => $this->courseOneId,
            'period_id' => $this->periodId,
            'offering_id' => null,
            'status' => 'draft',
            'requested_at' => null,
            'assessed_at' => null,
            'decided_at' => null,
            'decided_by' => null,
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->registrarUser);

        $this->postJson("/api/faculty/class-schedules/{$this->offeringOneId}/students", [
            'student_user_id' => $this->studentA->id,
            'note' => 'Manual add from calendar',
        ])->assertOk();

        $this->assertDatabaseHas('enrollments', [
            'id' => $existingEnrollmentId,
            'offering_id' => $this->offeringOneId,
            'status' => 'unofficial',
        ]);

        $this->assertDatabaseHas('enrollment_action_logs', [
            'enrollment_id' => $existingEnrollmentId,
            'offering_id' => $this->offeringOneId,
            'student_user_id' => $this->studentA->id,
            'acted_by_user_id' => $this->registrarUser->id,
            'action' => 'add',
            'from_status' => 'draft',
            'to_status' => 'unofficial',
            'note' => 'Manual add from calendar',
        ]);

        $this->postJson("/api/faculty/class-schedules/{$this->offeringOneId}/students", [
            'student_user_id' => $this->studentB->id,
            'note' => 'Should fail at capacity',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Selected section is already full.');
    }

    public function test_verify_and_unverify_transition_status_and_write_audit_logs(): void
    {
        $enrollmentId = (int) DB::table('enrollments')->insertGetId([
            'user_id' => $this->studentA->id,
            'course_id' => $this->courseOneId,
            'period_id' => $this->periodId,
            'offering_id' => $this->offeringOneId,
            'status' => 'unofficial',
            'requested_at' => now(),
            'assessed_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->registrarUser);

        $this->patchJson("/api/faculty/class-schedules/{$this->offeringOneId}/students/{$enrollmentId}", [
            'action' => 'verify',
            'note' => 'Verified by registrar',
        ])->assertOk();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollmentId,
            'status' => 'official',
            'decided_by' => $this->registrarUser->id,
        ]);

        $this->assertDatabaseHas('enrollment_action_logs', [
            'enrollment_id' => $enrollmentId,
            'action' => 'verify',
            'from_status' => 'unofficial',
            'to_status' => 'official',
            'note' => 'Verified by registrar',
        ]);

        $this->patchJson("/api/faculty/class-schedules/{$this->offeringOneId}/students/{$enrollmentId}", [
            'action' => 'unverify',
            'note' => 'Reverted by registrar',
        ])->assertOk();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollmentId,
            'status' => 'unofficial',
            'decided_by' => null,
        ]);

        $this->assertDatabaseHas('enrollment_action_logs', [
            'enrollment_id' => $enrollmentId,
            'action' => 'unverify',
            'from_status' => 'official',
            'to_status' => 'unofficial',
            'note' => 'Reverted by registrar',
        ]);
    }

    public function test_remove_rejects_official_and_allows_unofficial_with_audit_log(): void
    {
        $officialEnrollmentId = (int) DB::table('enrollments')->insertGetId([
            'user_id' => $this->studentA->id,
            'course_id' => $this->courseOneId,
            'period_id' => $this->periodId,
            'offering_id' => $this->offeringOneId,
            'status' => 'official',
            'requested_at' => now(),
            'assessed_at' => now(),
            'decided_at' => now(),
            'decided_by' => $this->registrarUser->id,
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unofficialEnrollmentId = (int) DB::table('enrollments')->insertGetId([
            'user_id' => $this->studentB->id,
            'course_id' => $this->courseOneId,
            'period_id' => $this->periodId,
            'offering_id' => $this->offeringOneId,
            'status' => 'unofficial',
            'requested_at' => now(),
            'assessed_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->registrarUser);

        $this->deleteJson("/api/faculty/class-schedules/{$this->offeringOneId}/students/{$officialEnrollmentId}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only draft or unofficial rows can be removed.');

        $this->deleteJson("/api/faculty/class-schedules/{$this->offeringOneId}/students/{$unofficialEnrollmentId}")
            ->assertOk();

        $this->assertDatabaseMissing('enrollments', ['id' => $unofficialEnrollmentId]);
        $this->assertDatabaseHas('enrollment_action_logs', [
            'offering_id' => $this->offeringOneId,
            'student_user_id' => $this->studentB->id,
            'action' => 'remove',
            'from_status' => 'unofficial',
            'to_status' => null,
        ]);
    }

    public function test_registrar_can_manage_rooms_and_delete_requires_force_confirmation(): void
    {
        Sanctum::actingAs($this->instructorUser);
        $this->postJson('/api/faculty/class-schedules/rooms', [
            'room_code' => 'ROOM999',
            'title' => 'Forbidden Room',
        ])->assertStatus(403);

        Sanctum::actingAs($this->registrarUser);

        $created = $this->postJson('/api/faculty/class-schedules/rooms', [
            'room_code' => 'ROOM101',
            'title' => 'Room 101',
            'description' => 'Registrar managed room',
            'icon_key' => 'presentation',
            'building' => 'A',
            'capacity' => 45,
            'is_active' => true,
        ])->assertStatus(201)->json('item');

        $this->assertNotNull($created);
        $roomId = (int) ($created['id'] ?? 0);
        $this->assertGreaterThan(0, $roomId);

        $this->patchJson("/api/faculty/class-schedules/rooms/{$roomId}", [
            'title' => 'Room 101 Updated',
            'capacity' => 48,
        ])->assertOk();

        $this->getJson("/api/faculty/class-schedules/rooms/availability?period_id={$this->periodId}&day_of_week=Mon&start_time=08:30&end_time=09:00")
            ->assertOk()
            ->assertJsonPath('has_slot', true);

        $this->deleteJson("/api/faculty/class-schedules/rooms/{$roomId}")
            ->assertStatus(422);

        $preview = $this->deleteJson("/api/faculty/class-schedules/rooms/{$roomId}?preview=1")
            ->assertOk()
            ->json('impact');
        $this->assertIsArray($preview);

        $this->deleteJson("/api/faculty/class-schedules/rooms/{$roomId}", [
            'confirm_force' => true,
            'confirm_text' => 'WRONGCODE',
        ])->assertStatus(422);

        $this->deleteJson("/api/faculty/class-schedules/rooms/{$roomId}", [
            'confirm_force' => true,
            'confirm_text' => 'ROOM101',
        ])->assertOk();

        $this->assertDatabaseHas('scheduling_action_logs', [
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'create',
            'acted_by_user_id' => $this->registrarUser->id,
        ]);

        $this->assertDatabaseHas('scheduling_action_logs', [
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'delete',
            'acted_by_user_id' => $this->registrarUser->id,
        ]);
    }

    public function test_schedule_update_writes_scheduling_action_log(): void
    {
        Sanctum::actingAs($this->registrarUser);

        $this->patchJson("/api/faculty/class-schedules/{$this->offeringOneId}", [
            'day_of_week' => 'Tue',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room_id' => $this->roomOneId,
            'teacher_id' => $this->teacherOneId,
            'section_id' => $this->sectionOneId,
        ])->assertOk();

        $this->assertDatabaseHas('scheduling_action_logs', [
            'entity_type' => 'offering',
            'entity_id' => $this->offeringOneId,
            'action' => 'update',
            'acted_by_user_id' => $this->registrarUser->id,
            'offering_id' => $this->offeringOneId,
            'room_id' => $this->roomOneId,
            'period_id' => $this->periodId,
        ]);
    }

    private function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        FacultyWorkflow::syncPermissionCatalog();
    }

    private function configureTestConnection(): void
    {
        $default = (string) config('database.default', 'sqlite');
        $drivers = class_exists(\PDO::class) ? \PDO::getAvailableDrivers() : [];

        if ($default !== 'sqlite' || in_array('sqlite', $drivers, true)) {
            return;
        }

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', env('DB_HOST', '127.0.0.1'));
        config()->set('database.connections.mysql.port', env('DB_PORT', 3306));
        config()->set('database.connections.mysql.username', env('DB_USERNAME', 'root'));
        config()->set('database.connections.mysql.password', env('DB_PASSWORD', ''));

        $database = (string) config('database.connections.mysql.database', '');
        if ($database === '' || $database === ':memory:') {
            config()->set('database.connections.mysql.database', 'tclass_db');
        }

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function seedFixtureData(): void
    {
        $registrarPositionId = (int) DB::table('faculty_positions')->insertGetId([
            'code' => 'registrar',
            'title' => 'Registrar',
            'category' => 'Academic Administration',
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $instructorPositionId = (int) DB::table('faculty_positions')->insertGetId([
            'code' => 'instructor_i',
            'title' => 'Instructor I',
            'category' => 'Teaching',
            'sort_order' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->registrarUser = User::factory()->create([
            'name' => 'Registrar Faculty',
            'email' => 'registrar.test@tclass.local',
        ]);
        $this->instructorUser = User::factory()->create([
            'name' => 'Instructor Faculty',
            'email' => 'instructor.test@tclass.local',
        ]);
        $this->studentA = User::factory()->create([
            'name' => 'Student A',
            'email' => 'student.a@tclass.local',
            'student_number' => 'S-0001',
        ]);
        $this->studentB = User::factory()->create([
            'name' => 'Student B',
            'email' => 'student.b@tclass.local',
            'student_number' => 'S-0002',
        ]);
        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin.test@tclass.local',
        ]);

        DB::table('portal_user_roles')->insert([
            [
                'user_id' => $this->registrarUser->id,
                'role' => 'faculty',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->instructorUser->id,
                'role' => 'faculty',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->studentA->id,
                'role' => 'student',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->studentB->id,
                'role' => 'student',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->adminUser->id,
                'role' => 'admin',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->teacherOneId = (int) DB::table('schedule_teachers')->insertGetId([
            'user_id' => $this->instructorUser->id,
            'employee_code' => 'T-1001',
            'full_name' => 'Prof. Instructor One',
            'email' => 'teacher.one@tclass.local',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->teacherTwoId = (int) DB::table('schedule_teachers')->insertGetId([
            'user_id' => null,
            'employee_code' => 'T-1002',
            'full_name' => 'Prof. Instructor Two',
            'email' => 'teacher.two@tclass.local',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('faculty_profiles')->insert([
            [
                'user_id' => $this->registrarUser->id,
                'employee_id' => 'REG-0001',
                'department' => 'Office of the Registrar',
                'position_id' => $registrarPositionId,
                'schedule_teacher_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->instructorUser->id,
                'employee_id' => 'INS-0001',
                'department' => 'College of IT',
                'position_id' => $instructorPositionId,
                'schedule_teacher_id' => $this->teacherOneId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        FacultyWorkflow::assignTemplate($this->registrarUser, FacultyWorkflow::TEMPLATE_REGISTRAR);
        FacultyWorkflow::assignTemplate($this->instructorUser, FacultyWorkflow::TEMPLATE_INSTRUCTOR);

        $this->periodId = (int) DB::table('enrollment_periods')->insertGetId([
            'name' => '1st Semester AY 2026-2027',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->courseOneId = (int) DB::table('courses')->insertGetId([
            'code' => 'IT101',
            'title' => 'Intro to IT',
            'units' => 3,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $courseTwoId = (int) DB::table('courses')->insertGetId([
            'code' => 'IT102',
            'title' => 'Advanced IT',
            'units' => 3,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sectionOneId = (int) DB::table('schedule_sections')->insertGetId([
            'program_name' => 'BS Information Technology',
            'year_level' => 1,
            'section_code' => 'BSIT-1A',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionTwoId = (int) DB::table('schedule_sections')->insertGetId([
            'program_name' => 'BS Information Technology',
            'year_level' => 1,
            'section_code' => 'BSIT-1B',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->roomOneId = (int) DB::table('schedule_rooms')->insertGetId([
            'building' => 'L',
            'room_code' => 'L306A',
            'title' => 'Room 101',
            'description' => 'Seed room',
            'icon_key' => 'presentation',
            'capacity' => 40,
            'is_active' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roomTwoId = (int) DB::table('schedule_rooms')->insertGetId([
            'building' => 'L',
            'room_code' => 'L206',
            'title' => 'Room 206',
            'description' => 'Seed room',
            'icon_key' => 'monitor',
            'capacity' => 40,
            'is_active' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->offeringOneId = (int) DB::table('class_offerings')->insertGetId([
            'period_id' => $this->periodId,
            'course_id' => $this->courseOneId,
            'section_id' => $this->sectionOneId,
            'teacher_id' => $this->teacherOneId,
            'room_id' => $this->roomOneId,
            'day_of_week' => 'Mon',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'schedule_text' => 'Mon 08:00 AM - 09:00 AM',
            'capacity' => 2,
            'is_active' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->offeringTwoId = (int) DB::table('class_offerings')->insertGetId([
            'period_id' => $this->periodId,
            'course_id' => $courseTwoId,
            'section_id' => $sectionTwoId,
            'teacher_id' => $this->teacherTwoId,
            'room_id' => $roomTwoId,
            'day_of_week' => 'Mon',
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
            'schedule_text' => 'Mon 09:30 AM - 10:30 AM',
            'capacity' => 40,
            'is_active' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resetTables(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'enrollment_action_logs',
            'enrollments',
            'class_offerings',
            'scheduling_action_logs',
            'schedule_rooms',
            'schedule_sections',
            'schedule_teachers',
            'courses',
            'enrollment_periods',
            'faculty_permission_overrides',
            'faculty_profiles',
            'faculty_positions',
            'portal_user_roles',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
            'roles',
            'permissions',
            'users',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('student_number')->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('must_change_password')->default(false);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'mhp_primary');
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['role_id', 'model_id', 'model_type'], 'mhr_primary');
            });
        }

        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id'], 'rhp_primary');
            });
        }

        if (! Schema::hasTable('portal_user_roles')) {
            Schema::create('portal_user_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('role');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'role']);
            });
        }

        if (! Schema::hasTable('faculty_positions')) {
            Schema::create('faculty_positions', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title')->unique();
                $table->string('category')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schedule_teachers')) {
            Schema::create('schedule_teachers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('employee_code')->nullable()->unique();
                $table->string('full_name');
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('faculty_profiles')) {
            Schema::create('faculty_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->string('employee_id')->nullable()->unique();
                $table->string('department')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('schedule_teacher_id')->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('faculty_permission_overrides')) {
            Schema::create('faculty_permission_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('permission_name');
                $table->boolean('is_allowed')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'permission_name'], 'faculty_permission_override_unique');
            });
        }

        if (! Schema::hasTable('enrollment_periods')) {
            Schema::create('enrollment_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->decimal('units', 5, 2)->default(3);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schedule_sections')) {
            Schema::create('schedule_sections', function (Blueprint $table) {
                $table->id();
                $table->string('program_name');
                $table->unsignedTinyInteger('year_level')->default(1);
                $table->string('section_code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schedule_rooms')) {
            Schema::create('schedule_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('building')->nullable();
                $table->string('room_code')->unique();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('icon_key')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('class_offerings')) {
            Schema::create('class_offerings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('period_id');
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('section_id');
                $table->unsignedBigInteger('teacher_id');
                $table->unsignedBigInteger('room_id');
                $table->string('day_of_week', 3);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('schedule_text')->nullable();
                $table->unsignedInteger('capacity')->default(40);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('scheduling_action_logs')) {
            Schema::create('scheduling_action_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->string('action');
                $table->unsignedBigInteger('acted_by_user_id')->nullable();
                $table->unsignedBigInteger('room_id')->nullable();
                $table->unsignedBigInteger('offering_id')->nullable();
                $table->unsignedBigInteger('period_id')->nullable();
                $table->text('before_snapshot')->nullable();
                $table->text('after_snapshot')->nullable();
                $table->string('note')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('enrollments')) {
            Schema::create('enrollments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('period_id')->nullable();
                $table->unsignedBigInteger('offering_id')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('assessed_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->string('remarks')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'course_id', 'period_id'], 'enrollments_user_course_period_unique');
            });
        }

        if (! Schema::hasTable('enrollment_action_logs')) {
            Schema::create('enrollment_action_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('enrollment_id')->nullable();
                $table->unsignedBigInteger('offering_id')->nullable();
                $table->unsignedBigInteger('student_user_id')->nullable();
                $table->unsignedBigInteger('acted_by_user_id')->nullable();
                $table->string('action', 20);
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->string('note')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
