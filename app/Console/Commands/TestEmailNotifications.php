<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Task;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusUpdatedNotification;
use App\Notifications\TaskCommentNotification;
use App\Notifications\TaskInvitationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class TestEmailNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-notifications {--user-id= : ID of the user to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email notifications by sending test emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        
        if (!$userId) {
            $userId = $this->ask('Enter the user ID to test with:');
        }

        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Testing email notifications for user: {$user->name} ({$user->email})");

        // Create a test task
        $task = Task::create([
            'title' => 'Test Task for Email Notifications',
            'description' => 'This is a test task to verify email notifications are working properly.',
            'priority' => 'medium',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->info("Created test task: {$task->title}");

        // Test Task Assigned Notification
        $this->info('Testing Task Assigned Notification...');
        $user->notify(new TaskAssignedNotification($task, $user));
        $this->info('✓ Task Assigned Notification sent');

        // Test Task Status Updated Notification
        $this->info('Testing Task Status Updated Notification...');
        $user->notify(new TaskStatusUpdatedNotification($task, $user, 'pending', 'in_progress'));
        $this->info('✓ Task Status Updated Notification sent');

        // Test Task Comment Notification
        $this->info('Testing Task Comment Notification...');
        $comment = \App\Models\TaskInteraction::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => 'comment',
            'content' => 'This is a test comment to verify email notifications.',
            'status' => 'accepted',
        ]);
        $user->notify(new TaskCommentNotification($task, $comment, $user));
        $this->info('✓ Task Comment Notification sent');

        // Test Task Invitation Notification
        $this->info('Testing Task Invitation Notification...');
        $invitation = \App\Models\TaskInteraction::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => 'invitation',
            'metadata' => ['role' => 'collaborator'],
            'status' => 'pending',
        ]);
        $user->notify(new TaskInvitationNotification($task, $invitation, $user));
        $this->info('✓ Task Invitation Notification sent');

        // Clean up test task
        $task->delete();
        $this->info("Cleaned up test task");

        $this->info("\n🎉 All email notification tests completed!");
        $this->info("Check your email inbox or mail logs to verify the notifications were sent.");
        
        if (config('mail.default') === 'log') {
            $this->info("Using log driver - check storage/logs/laravel.log for email content.");
        } elseif (config('mail.default') === 'array') {
            $this->info("Using array driver - emails are captured in memory for testing.");
        }

        return 0;
    }
}
