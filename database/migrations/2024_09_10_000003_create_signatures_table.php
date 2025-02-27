<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSignaturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('signatures', function (Blueprint $table) {
            
            addMetaData($table);

            $table->foreignId('email_account_id')->constrained();
            $table->tinyInteger('is_auto_insert')->nullable();
            $table->string('name')->nullable();
            $table->string('image')->nullable();
            $table->longText('html')->nullable();
            $table->integer('width')->nullable();
            $table->tinyInteger('only_image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('signatures');
    }
}
