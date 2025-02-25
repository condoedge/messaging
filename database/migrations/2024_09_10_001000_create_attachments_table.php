<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attachments', function (Blueprint $table) {

            addMetaData($table);

            $table->foreignId('team_id')->nullable()->constrained();
            $table->foreignId('message_id')->constrained();
            $table->string('path', 1000)->nullable();
            $table->string('name')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attachments');
    }
}
