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
        Schema::table('jira_issues', function (Blueprint $table) {
            $table->string('sprint')->nullable()->after('issue_type');
            $table->string('epic')->nullable()->after('sprint');
        });
    }

    public function down(): void
    {
        Schema::table('jira_issues', function (Blueprint $table) {
            $table->dropColumn(['sprint', 'epic']);
        });
    }
};
