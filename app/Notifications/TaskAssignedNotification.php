<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $assignedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, User $assignedBy)
    {
        $this->task = $task;
        $this->assignedBy = $assignedBy;
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

        return (new MailMessage)
            ->subject('New Task Assignment: ' . $this->task->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been assigned to a new task.')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Assigned by:** ' . $this->assignedBy->name)
            ->line('**Priority:** ' . ucfirst($this->task->priority))
            ->line('**Status:** ' . ucfirst(str_replace('_', ' ', $this->task->status)))
            ->when($this->task->due_date, function ($message) {
                return $message->line('**Due Date:** ' . $this->task->due_date->format('M d, Y'));
            })
            ->when($this->task->description, function ($message) {
                return $message->line('**Description:** ' . $this->task->description);
            })
            ->action('View Task', $url)
            ->line('Please review the task details and update the status as you progress.')
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
            'assigned_by_id' => $this->assignedBy->id,
            'assigned_by_name' => $this->assignedBy->name,
            'type' => 'task_assigned',
        ];
    }
}
