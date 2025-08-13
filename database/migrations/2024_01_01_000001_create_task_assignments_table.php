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
        Schema::create('task_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['assignment', 'comment', 'invitation', 'notification'])->default('assignment');
            $table->enum('role', ['assignee', 'collaborator', 'viewer'])->nullable();
            $table->text('content')->nullable(); // For comments, invitation messages, notification messages
            $table->json('metadata')->nullable(); // For additional data like mentions, invitation status, etc.
            $table->enum('status', ['pending', 'accepted', 'declined', 'read', 'unread'])->default('pending');
            $table->foreignId('parent_id')->nullable()->constrained('task_interactions')->onDelete('cascade'); // For comment replies
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['task_id', 'type']);
            $table->index(['user_id', 'type']);
            $table->index(['type', 'status']);
            $table->index(['parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_interactions');
    }
}; 