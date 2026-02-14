<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('nusantara.table_prefix', 'nusantara_');

        Schema::create($prefix . 'provinces', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('name', 100)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });

        Schema::create($prefix . 'regencies', function (Blueprint $table) use ($prefix) {
            $table->string('code', 5)->primary();
            $table->string('province_code', 2)->index();
            $table->string('name', 100)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->foreign('province_code')
                ->references('code')
                ->on($prefix . 'provinces')
                ->cascadeOnDelete();
        });

        Schema::create($prefix . 'districts', function (Blueprint $table) use ($prefix) {
            $table->string('code', 8)->primary();
            $table->string('regency_code', 5)->index();
            $table->string('name', 100)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->foreign('regency_code')
                ->references('code')
                ->on($prefix . 'regencies')
                ->cascadeOnDelete();
        });

        Schema::create($prefix . 'villages', function (Blueprint $table) use ($prefix) {
            $table->string('code', 13)->primary();
            $table->string('district_code', 8)->index();
            $table->string('name', 100)->index();
            $table->string('postal_code', 5)->nullable()->index();
            $table->foreign('district_code')
                ->references('code')
                ->on($prefix . 'districts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('nusantara.table_prefix', 'nusantara_');
        Schema::dropIfExists($prefix . 'villages');
        Schema::dropIfExists($prefix . 'districts');
        Schema::dropIfExists($prefix . 'regencies');
        Schema::dropIfExists($prefix . 'provinces');
    }
};
