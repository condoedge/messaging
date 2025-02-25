<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThreadBoxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('thread_boxes', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('thread_id')->constrained();
            $table->foreignId('email_account_id')->constrained(); //TODO WHEN MOVING TO CONDOEDGE, fill this data
            $table->integer('box')->default(\Condoedge\Messaging\Models\CustomInbox\ThreadBox::BOX_ARCHIVE);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('thread_boxes');
    }
}
