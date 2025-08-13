<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $comment;
    protected $mentionedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, TaskInteraction $comment, User $mentionedBy)
    {
        $this->task = $task;
        $this->comment = $comment;
        $this->mentionedBy = $mentionedBy;
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
            ->subject('You were mentioned in a comment on: ' . $this->task->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You were mentioned in a comment on a task.')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Mentioned by:** ' . $this->mentionedBy->name)
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
            'mentioned_by_id' => $this->mentionedBy->id,
            'mentioned_by_name' => $this->mentionedBy->name,
            'type' => 'comment_mention',
        ];
    }
}
