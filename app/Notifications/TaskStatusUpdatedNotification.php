<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $updatedBy;
    protected $oldStatus;
    protected $newStatus;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, User $updatedBy, string $oldStatus, string $newStatus)
    {
        $this->task = $task;
        $this->updatedBy = $updatedBy;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
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
        $statusLabels = [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed'
        ];

        return (new MailMessage)
            ->subject('Task Status Updated: ' . $this->task->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A task status has been updated.')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Updated by:** ' . $this->updatedBy->name)
            ->line('**Status changed from:** ' . ($statusLabels[$this->oldStatus] ?? ucfirst($this->oldStatus)))
            ->line('**Status changed to:** ' . ($statusLabels[$this->newStatus] ?? ucfirst($this->newStatus)))
            ->line('**Updated at:** ' . now()->format('M d, Y \a\t g:i A'))
            ->action('View Task', $url)
            ->line('Please review the task and take any necessary actions.')
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
            'updated_by_id' => $this->updatedBy->id,
            'updated_by_name' => $this->updatedBy->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'type' => 'task_status_updated',
        ];
    }
}
