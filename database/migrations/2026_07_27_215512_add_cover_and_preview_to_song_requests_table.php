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
        Schema::table('song_requests', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('artist');
            $table->string('preview_url')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'preview_url']);
        });
    }
};
