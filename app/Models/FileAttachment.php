<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'interaction_id',
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /**
     * Get the task this attachment belongs to
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who uploaded this attachment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the interaction (comment) this attachment belongs to, if any
     */
    public function interaction()
    {
        return $this->belongsTo(TaskInteraction::class, 'interaction_id');
    }

    /**
     * Get the full URL for the file
     */
    public function getUrlAttribute()
    {
        // Make sure we have a valid file path
        if (empty($this->file_path)) {
            return null;
        }
        
        // Generate the full URL with the correct APP_URL
        return url('storage/' . $this->file_path);
    }
}