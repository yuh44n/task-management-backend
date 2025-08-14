<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskInteraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationResponseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $invitation;
    protected $responder;
    protected $response;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, TaskInteraction $invitation, User $responder, string $response)
    {
        $this->task = $task;
        $this->invitation = $invitation;
        $this->responder = $responder;
        $this->response = $response;
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
        $responseText = $this->response === 'accepted' ? 'accepted' : 'declined';
        $subject = 'Invitation ' . ucfirst($responseText) . ': ' . $this->task->title;

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Someone has responded to your task invitation.')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Response by:** ' . $this->responder->name)
            ->line('**Response:** ' . ucfirst($responseText))
            ->line('**Responded at:** ' . now()->format('M d, Y \a\t g:i A'));

        if ($this->response === 'accepted') {
            $mailMessage->line('Great! ' . $this->responder->name . ' has joined your task team.');
        } else {
            $mailMessage->line($this->responder->name . ' has declined the invitation.');
        }

        return $mailMessage
            ->action('View Task', $url)
            ->line('Click the button above to view the task and manage participants.')
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
            'responder_id' => $this->responder->id,
            'responder_name' => $this->responder->name,
            'response' => $this->response,
            'type' => 'invitation_response',
        ];
    }
}
