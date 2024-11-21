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
        Schema::create('google_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained();

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->bigInteger('token_expires')->nullable();
            $table->string('user_email')->nullable();
            $table->string('display_name')->nullable();
            $table->string('user_timezone')->nullable();

            $table->string('ms_user_id', 500)->nullable();
            $table->text('inbox_mailbox_id')->nullable();
            $table->text('sent_mailbox_id')->nullable();
            $table->text('archived_mailbox_id')->nullable();
            $table->text('deleted_mailbox_id')->nullable();
            $table->text('draft_mailbox_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_google_id')->nullable()->constrained('google_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_tokens');
    }
};
