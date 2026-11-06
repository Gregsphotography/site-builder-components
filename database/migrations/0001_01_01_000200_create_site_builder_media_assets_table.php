<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_builder_media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('disk');
            $table->string('path', 512)->unique();
            $table->string('name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('alt')->nullable();
            $table->unsignedBigInteger('write_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_media_assets');
    }
};
