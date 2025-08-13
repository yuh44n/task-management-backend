<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $comment;
    protected $commenter;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, TaskInteraction $comment, User $commenter)
    {
        $this->task = $task;
        $this->comment = $comment;
        $this->commenter = $commenter;
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
        $commentPreview = strlen($this->comment->content) > 100 
            ? substr($this->comment->content, 0, 100) . '...' 
            : $this->comment->content;

        return (new MailMessage)
            ->subject('New Comment on Task: ' . $this->task->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new comment has been added to a task.')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Comment by:** ' . $this->commenter->name)
            ->line('**Comment:** ' . $commentPreview)
            ->line('**Commented at:** ' . $this->comment->created_at->format('M d, Y \a\t g:i A'))
            ->action('View Task', $url)
            ->line('Click the button above to view the full comment and respond if needed.')
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
            'comment_id' => $this->comment->id,
            'commenter_id' => $this->commenter->id,
            'commenter_name' => $this->commenter->name,
            'type' => 'task_comment',
        ];
    }
}
