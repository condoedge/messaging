<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThreadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('threads', function (Blueprint $table) {
            
            addMetaData($table);

            $table->integer('type')->default(1);
            $table->string('subject')->nullable();
            $table->tinyInteger('flag_color')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->integer('db_message_count')->nullable();
            $table->integer('db_attachment_count')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('threads');
    }
}
