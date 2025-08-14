<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create regular user
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        // Create some sample tasks
        $admin = User::where('email', 'admin@example.com')->first();
        $john = User::where('email', 'john@example.com')->first();

        // Sample tasks
        $tasks = [
            [
                'title' => 'Complete Project Documentation',
                'description' => 'Write comprehensive documentation for the task management system',
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => now()->addDays(7),
                'created_by' => $admin->id,
            ],
            [
                'title' => 'Review Code Changes',
                'description' => 'Review and approve recent code changes in the repository',
                'priority' => 'medium',
                'status' => 'pending',
                'due_date' => now()->addDays(3),
                'created_by' => $john->id,
            ],
            [
                'title' => 'Setup Development Environment',
                'description' => 'Configure development environment for new team members',
                'priority' => 'low',
                'status' => 'completed',
                'due_date' => now()->subDays(2),
                'created_by' => $admin->id,
            ],
        ];

        foreach ($tasks as $taskData) {
            Task::create($taskData);
        }
    }
}
