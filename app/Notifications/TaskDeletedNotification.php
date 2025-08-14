<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $taskTitle;
    protected $deletedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $taskTitle, User $deletedBy)
    {
        $this->taskTitle = $taskTitle;
        $this->deletedBy = $deletedBy;
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
        return (new MailMessage)
            ->subject('Task Deleted: ' . $this->taskTitle)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A task you were involved with has been deleted.')
            ->line('**Task:** ' . $this->taskTitle)
            ->line('**Deleted by:** ' . $this->deletedBy->name)
            ->line('**Deleted at:** ' . now()->format('M d, Y \a\t g:i A'))
            ->line('This task is no longer available. If you have any questions, please contact the person who deleted it.')
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
            'task_title' => $this->taskTitle,
            'deleted_by_id' => $this->deletedBy->id,
            'deleted_by_name' => $this->deletedBy->name,
            'type' => 'task_deleted',
        ];
    }
}
