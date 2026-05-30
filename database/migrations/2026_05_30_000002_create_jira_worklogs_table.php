<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_worklogs', function (Blueprint $table) {
            $table->id();
            $table->string('jira_worklog_id')->unique();
            $table->string('issue_key');
            $table->string('author_account_id');
            $table->string('author_display_name');
            $table->integer('time_spent_seconds');
            $table->timestamp('started_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['author_account_id', 'started_at']);
            $table->index('issue_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_worklogs');
    }
};
