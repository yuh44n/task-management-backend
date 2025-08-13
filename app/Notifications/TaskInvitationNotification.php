<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $invitation;
    protected $inviter;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, TaskInteraction $invitation, User $inviter)
    {
        $this->task = $task;
        $this->invitation = $invitation;
        $this->inviter = $inviter;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/tasks/' . $this->task->id);
        $role = $this->invitation->metadata['role'] ?? 'collaborator';
        $message = $this->invitation->metadata['message'] ?? '';

        $mailMessage = (new MailMessage)
            ->subject('Task Collaboration Invitation: ' . $this->task->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been invited to collaborate on a task.')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Invited by:** ' . $this->inviter->name)
            ->line('**Role:** ' . ucfirst($role))
            ->line('**Priority:** ' . ucfirst($this->task->priority))
            ->line('**Status:** ' . ucfirst(str_replace('_', ' ', $this->task->status)));

        if ($message) {
            $mailMessage->line('**Message from ' . $this->inviter->name . ':** ' . $message);
        }

        if ($this->task->due_date) {
            $mailMessage->line('**Due Date:** ' . $this->task->due_date->format('M d, Y'));
        }

        if ($this->task->description) {
            $mailMessage->line('**Description:** ' . $this->task->description);
        }

        return $mailMessage
            ->action('View Task', $url)
            ->line('Please review the task details and respond to the invitation.')
            ->salutation('Best regards, Task Management Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'invitation_id' => $this->invitation->id,
            'inviter_id' => $this->inviter->id,
            'inviter_name' => $this->inviter->name,
            'role' => $this->invitation->metadata['role'] ?? 'collaborator',
            'type' => 'task_invitation',
        ];
    }
}
