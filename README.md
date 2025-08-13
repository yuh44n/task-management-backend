# Task Management System - Backend

A Laravel 10 API backend for the online task management system.

## Features

- **User Authentication**: Register, login, and logout with Laravel Sanctum
- **Role-based Access**: Admin and user roles with appropriate permissions
- **Task Management**: Create, read, update, delete tasks with priorities and status
- **User Collaboration**: Assign users to tasks and collaborate
- **Task Comments**: Add comments and discussions on tasks
- **Search & Filter**: Search tasks and filter by status, priority, etc.
- **Admin Dashboard**: Admin-only features for user and task management

## Setup Instructions

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL/PostgreSQL
- Node.js (for frontend)

### Installation

1. **Clone and install dependencies**
   ```bash
   cd task-management-backend
   composer install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure database**
   Edit `.env` file with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=task_management
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

4. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start the server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000/api`

## API Endpoints

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user (authenticated)
- `GET /api/user` - Get authenticated user (authenticated)

### Tasks
- `GET /api/tasks` - Get all tasks (authenticated)
- `POST /api/tasks` - Create a new task (authenticated)
- `GET /api/tasks/{id}` - Get a specific task (authenticated)
- `PUT /api/tasks/{id}` - Update a task (authenticated)
- `DELETE /api/tasks/{id}` - Delete a task (authenticated)
- `GET /api/tasks/users/list` - Get users for assignment (authenticated)

### Comments
- `GET /api/tasks/{task}/comments` - Get task comments (authenticated)
- `POST /api/tasks/{task}/comments` - Add a comment (authenticated)
- `PUT /api/comments/{id}` - Update a comment (authenticated)
- `DELETE /api/comments/{id}` - Delete a comment (authenticated)

### Admin (Admin only)
- `GET /api/admin/users` - Get all users
- `GET /api/admin/tasks` - Get all tasks
- `GET /api/admin/stats` - Get dashboard statistics
- `PUT /api/admin/users/{id}/role` - Update user role
- `DELETE /api/admin/users/{id}` - Delete user

## Database Structure

### Tables
- **users**: User accounts with roles
- **tasks**: Main task data
- **task_assignments**: User-task relationships
- **task_comments**: Task comments and discussions

### Sample Data
After running seeders, you'll have:
- Admin user: `admin@example.com` / `password`
- Regular user: `john@example.com` / `password`
- Sample tasks for testing

## Authentication

The API uses Laravel Sanctum for authentication. Include the token in requests:
```
Authorization: Bearer {token}
```

## CORS Configuration

CORS is configured to allow requests from the frontend. Update `config/cors.php` for production.

## Security Features

- Input validation on all endpoints
- Role-based authorization
- CSRF protection
- SQL injection prevention
- XSS protection

## Development

- Run tests: `php artisan test`
- Clear cache: `php artisan cache:clear`
- Reset database: `php artisan migrate:fresh --seed`
