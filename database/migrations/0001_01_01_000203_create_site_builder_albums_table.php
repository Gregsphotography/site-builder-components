<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_builder_albums', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->json('draft');
            $table->json('published')->nullable();
            $table->string('published_slug', 160)->nullable()->unique();
            $table->string('published_title')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_version')->nullable();
            $table->unsignedBigInteger('write_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_albums');
    }
};
