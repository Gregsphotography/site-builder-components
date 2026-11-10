<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_builder_form_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('recipients');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('write_version')->default(0);
            $table->timestamps();
        });
        Schema::create('site_builder_form_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('profile_id')->index();
            $table->unsignedBigInteger('page_id')->nullable()->index();
            $table->string('locale', 32);
            $table->text('form_address');
            $table->string('form_title');
            $table->char('form_digest', 64);
            $table->uuid('request_token')->unique();
            $table->json('fields');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
        Schema::create('site_builder_form_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->unique();
            $table->json('recipients');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_form_notifications');
        Schema::dropIfExists('site_builder_form_submissions');
        Schema::dropIfExists('site_builder_form_profiles');
    }
};
