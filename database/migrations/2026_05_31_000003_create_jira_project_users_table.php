<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_project_users', function (Blueprint $table) {
            $table->id();
            $table->string('project_key');
            $table->string('account_id');
            $table->string('display_name');
            $table->boolean('active')->default(true);
            $table->string('source')->default('jira');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['project_key', 'account_id']);
            $table->index(['project_key', 'display_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_project_users');
    }
};
