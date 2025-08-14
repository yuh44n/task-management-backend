<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'parent_id',
        'comment',
        'mentions',
    ];

    protected $casts = [
        'mentions' => 'array',
    ];

    /**
     * Get the task for this comment
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who made this comment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for replies)
     */
    public function parent()
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    /**
     * Get replies to this comment
     */
    public function replies()
    {
        return $this->hasMany(TaskComment::class, 'parent_id');
    }

    /**
     * Get mentioned users
     */
    public function mentionedUsers()
    {
        if (!$this->mentions) {
            return collect();
        }
        
        return User::whereIn('id', $this->mentions)->get();
    }

    /**
     * Check if comment is a reply
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Check if comment has replies
     */
    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    /**
     * Get all replies recursively
     */
    public function getAllReplies()
    {
        return $this->replies()->with(['user', 'replies.user'])->get();
    }

    /**
     * Scope for top-level comments (not replies)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for replies only
     */
    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Extract mentions from comment text
     */
    public static function extractMentions($text): array
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Get user IDs from mention usernames
     */
    public static function getMentionedUserIds($text): array
    {
        $usernames = self::extractMentions($text);
        return User::whereIn('name', $usernames)->pluck('id')->toArray();
    }
} 