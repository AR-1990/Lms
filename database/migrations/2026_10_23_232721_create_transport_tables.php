<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17b (PLAN.md §18): vehicles (with insurance / fitness expiry), routes with a driver, attendant,
 * vehicle and monthly fee, stops with pickup / drop times, and which student rides from which stop (the
 * monthly fee challan adds the transport line). Money is in paisa. No foreign-key constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('vehicles', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('registration_no', 30)->index();
            $table->unsignedBigInteger('type_id')->nullable()->index();
            $table->string('make_model', 100)->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->unsignedBigInteger('asset_id')->nullable()->index();
            $table->date('insurance_expiry')->nullable()->index();
            $table->date('fitness_expiry')->nullable()->index();
            $table->date('route_permit_expiry')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('transport_routes', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->index();
            $table->unsignedBigInteger('driver_employee_id')->nullable()->index();
            $table->unsignedBigInteger('attendant_employee_id')->nullable()->index();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->bigInteger('monthly_fee')->default(0);
            $table->unsignedBigInteger('fee_head_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('transport_stops', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('transport_route_id')->index();
            $table->string('name', 100);
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->time('pickup_time')->nullable();
            $table->time('drop_time')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->bigInteger('fee_override')->nullable();
            $audit($table);
        });

        Schema::create('student_transport', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('transport_route_id')->index();
            $table->unsignedBigInteger('transport_stop_id')->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->date('from_date')->index();
            $table->date('to_date')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('remarks')->nullable();
            $audit($table);
        });

        $now = now();

        if (! DB::table('permissions')->where('slug', 'manage-transport')->exists()) {
            DB::table('permissions')->insert(['name' => 'Manage Transport', 'slug' => 'manage-transport', 'group' => 'transport', 'description' => 'Vehicles, routes, stops and which students ride the school buses.', 'created_at' => $now, 'updated_at' => $now]);
        }

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($roleId !== null) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-transport')->value('id')], ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-transport')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        foreach (['student_transport', 'transport_stops', 'transport_routes', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
