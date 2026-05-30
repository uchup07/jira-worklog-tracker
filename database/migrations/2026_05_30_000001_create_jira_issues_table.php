<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_key')->unique();
            $table->string('summary');
            $table->string('status');
            $table->string('project_key');
            $table->string('assignee_account_id')->nullable();
            $table->string('assignee_display_name')->nullable();
            $table->string('priority')->nullable();
            $table->string('issue_type');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index('project_key');
            $table->index('assignee_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_issues');
    }
};
