<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Constructor to ensure only admins can access
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isAdmin()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return $next($request);
        });
    }

    /**
     * Get all users (admin only)
     */
    public function getUsers()
    {
        $users = User::select('id', 'name', 'email', 'role', 'created_at')
                    ->withCount(['createdTasks', 'assignedTasks'])
                    ->latest()
                    ->paginate(10);

        return response()->json($users);
    }

    /**
     * Get all tasks (admin only)
     */
    public function getAllTasks(Request $request)
    {
        $query = Task::with(['creator', 'assignedUsers', 'comments.user']);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Filter by creator
        if ($request->has('creator_id')) {
            $query->createdBy($request->creator_id);
        }

        // Search functionality
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        $tasks = $query->latest()->paginate(10);

        return response()->json($tasks);
    }

    /**
     * Get dashboard statistics (admin only)
     */
    public function getDashboardStats()
    {
        $stats = [
            'total_users' => User::count(),
            'total_tasks' => Task::count(),
            'completed_tasks' => Task::where('status', 'completed')->count(),
            'pending_tasks' => Task::where('status', 'pending')->count(),
            'in_progress_tasks' => Task::where('status', 'in_progress')->count(),
            'overdue_tasks' => Task::where('due_date', '<', now())
                                ->where('status', '!=', 'completed')
                                ->count(),
            'recent_tasks' => Task::with('creator')->latest()->take(5)->get(),
            'recent_users' => User::latest()->take(5)->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Update user role (admin only)
     */
    public function updateUserRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:admin,user',
        ]);

        $user->update(['role' => $request->role]);

        return response()->json([
            'user' => $user,
            'message' => 'User role updated successfully'
        ]);
    }

    /**
     * Delete user (admin only)
     */
    public function deleteUser(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
} 