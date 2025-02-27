<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            
            addMetaData($table);

            $table->string('email_adr');
            $table->nullableMorphs('entity');
            $table->tinyInteger('is_mailbox')->nullable();
            $table->integer('unread_count')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_mailbox_id')->nullable()->constrained('email_accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_mailbox_id']);
            $table->dropColumn('current_mailbox_id');
        });

        Schema::dropIfExists('email_accounts');
    }
}
