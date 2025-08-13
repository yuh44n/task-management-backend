<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'role',
    ];

    /**
     * Get the task for this assignment
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user for this assignment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if assignment is for assignee
     */
    public function isAssignee(): bool
    {
        return $this->role === 'assignee';
    }

    /**
     * Check if assignment is for collaborator
     */
    public function isCollaborator(): bool
    {
        return $this->role === 'collaborator';
    }
} 