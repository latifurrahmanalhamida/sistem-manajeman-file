<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            
            // Kolom untuk menunjukkan folder ini milik divisi mana
            $table->foreignId('division_id')->constrained('divisions')->onDelete('cascade');
            
            // Kolom untuk menunjukkan siapa yang membuat folder
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Kolom untuk relasi sub-folder. Bisa null jika di root.
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};