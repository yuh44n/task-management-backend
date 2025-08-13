<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get tasks created by this user
     */
    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Get task assignments for this user
     */
    public function taskAssignments()
    {
        return $this->hasMany(TaskInteraction::class)->assignments();
    }

    /**
     * Get tasks assigned to this user
     */
    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_interactions')
                    ->wherePivot('type', 'assignment')
                    ->wherePivot('status', 'accepted')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Get comments by this user
     */
    public function comments()
    {
        return $this->hasMany(TaskInteraction::class)->comments();
    }

    /**
     * Get invitations sent by this user
     */
    public function sentInvitations()
    {
        return $this->hasMany(TaskInteraction::class)->invitations();
    }

    /**
     * Get invitations received by this user
     */
    public function receivedInvitations()
    {
        return $this->hasMany(TaskInteraction::class)->invitations();
    }

    /**
     * Get pending invitations for this user
     */
    public function pendingInvitations()
    {
        return $this->hasMany(TaskInteraction::class)->pendingInvitations();
    }

    /**
     * Get notifications for this user
     */
    public function notifications()
    {
        return $this->hasMany(TaskInteraction::class)->notifications();
    }

    /**
     * Get unread notifications for this user
     */
    public function unreadNotifications()
    {
        return $this->hasMany(TaskInteraction::class)->unreadNotifications();
    }

    /**
     * Get read notifications for this user
     */
    public function readNotifications()
    {
        return $this->hasMany(TaskInteraction::class)->notifications()->where('status', 'read');
    }

    /**
     * Get recent notifications for this user
     */
    public function recentNotifications($days = 7)
    {
        return $this->hasMany(TaskInteraction::class)
            ->notifications()
            ->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead()
    {
        $this->unreadNotifications()->update(['status' => 'read']);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadNotificationCount(): int
    {
        return $this->unreadNotifications()->count();
    }
}
