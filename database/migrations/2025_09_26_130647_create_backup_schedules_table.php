<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->enum('frequency', ['off', 'daily', 'weekly', 'monthly', 'yearly'])
                  ->default('off');           // frekuensi backup
            $table->time('time')->nullable(); // jam eksekusi
            $table->integer('day_of_week')->nullable();   // untuk weekly (0=minggu, 6=sabtu)
            $table->integer('day_of_month')->nullable();  // untuk monthly (1-31)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
