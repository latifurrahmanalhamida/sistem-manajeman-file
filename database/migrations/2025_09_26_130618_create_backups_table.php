<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');      // nama file backup
            $table->string('path');          // lokasi file backup
            $table->enum('schedule', ['manual', 'auto', 'daily', 'weekly', 'monthly', 'yearly'])
                  ->default('manual');      // jenis backup
            $table->bigInteger('size');      // ukuran file (bytes)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
