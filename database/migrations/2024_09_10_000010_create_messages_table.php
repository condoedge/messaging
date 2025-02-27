<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('thread_id')->constrained();
            $table->foreignId('sender_id')->constrained('email_accounts');
            $table->foreignId('message_id')->nullable()->constrained();
            $table->integer('type')->default(1);
            $table->string('uuid')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->string('summary')->nullable();
            $table->longText('text')->nullable(); //null when sending an attachment only
            $table->longText('html')->nullable(); //null when sending an attachment only
            $table->tinyInteger('is_draft')->nullable();
            $table->string('external_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
}
