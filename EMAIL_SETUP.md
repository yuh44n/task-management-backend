# Email Notifications Setup Guide

This guide explains how to set up email notifications for the Task Management System.

## Overview

The system now includes comprehensive email notifications for:
- Task assignments
- Task status updates
- Task comments
- Task invitations
- Comment mentions
- Invitation responses
- Task deletions

## Configuration

### 1. Environment Variables

Add the following to your `.env` file:

```env
# Application
APP_NAME="Task Management System"
APP_URL=http://localhost:8000

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@taskmanagement.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 2. Mail Service Options

#### Option A: Mailtrap (Development/Testing)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
```

#### Option B: Gmail SMTP
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
```

#### Option C: SendGrid
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_ENCRYPTION=tls
```

#### Option D: Amazon SES
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=us-east-1
```

### 3. Queue Configuration (Recommended for Production)

For better performance, configure queues:

```env
QUEUE_CONNECTION=database
```

Then run:
```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

## Testing Email Notifications

### 1. Test with Mailtrap
1. Sign up at [mailtrap.io](https://mailtrap.io)
2. Create an inbox
3. Copy the SMTP credentials to your `.env` file
4. Send a test notification

### 2. Test with Log Driver
For development, you can use the log driver to see emails in your log files:

```env
MAIL_MAILER=log
```

### 3. Test with Array Driver
For testing, use the array driver to capture emails in memory:

```env
MAIL_MAILER=array
```

## Notification Types

### Task Assignment
- **Trigger**: When a user is assigned to a task
- **Recipients**: Assigned user
- **Content**: Task details, assigner name, priority, due date

### Task Status Update
- **Trigger**: When task status changes
- **Recipients**: Task creator and assigned users
- **Content**: Old status, new status, updated by, timestamp

### Task Comment
- **Trigger**: When a comment is added to a task
- **Recipients**: Task creator and assigned users
- **Content**: Comment preview, commenter name, timestamp

### Task Invitation
- **Trigger**: When a user is invited to collaborate
- **Recipients**: Invited user
- **Content**: Task details, inviter name, role, custom message

### Comment Mention
- **Trigger**: When a user is mentioned in a comment
- **Recipients**: Mentioned user
- **Content**: Comment preview, mentioner name, task title

### Invitation Response
- **Trigger**: When an invitation is accepted/declined
- **Recipients**: Inviter
- **Content**: Response status, responder name, task title

### Task Deletion
- **Trigger**: When a task is deleted
- **Recipients**: Task participants
- **Content**: Task title, deleted by, timestamp

## Customization

### Email Templates
Email templates are located in:
- `resources/views/vendor/notifications/email.blade.php` - Main layout
- Individual notification classes in `app/Notifications/`

### Styling
The email layout uses inline CSS for maximum compatibility across email clients.

### Localization
To support multiple languages, update the notification classes to use Laravel's localization features.

## Troubleshooting

### Common Issues

1. **Emails not sending**
   - Check mail configuration in `.env`
   - Verify SMTP credentials
   - Check firewall/port restrictions

2. **Emails going to spam**
   - Configure SPF, DKIM, and DMARC records
   - Use a reputable mail service
   - Avoid spam trigger words

3. **Queue issues**
   - Ensure queue worker is running
   - Check queue configuration
   - Monitor queue logs

### Debug Mode
Enable debug mode to see detailed error messages:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Security Considerations

1. **Rate Limiting**: Implement rate limiting for email sending
2. **Authentication**: Ensure proper user authentication before sending emails
3. **Content Validation**: Validate email content to prevent injection attacks
4. **Unsubscribe**: Include unsubscribe links in emails (GDPR compliance)

## Performance Optimization

1. **Queues**: Use queues for asynchronous email sending
2. **Batch Processing**: Send emails in batches when possible
3. **Caching**: Cache user data to reduce database queries
4. **Monitoring**: Monitor email delivery rates and bounce rates
