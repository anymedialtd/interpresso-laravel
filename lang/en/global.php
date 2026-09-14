<?php

return [
    'unauthenticated' => 'User is not authenticated!',
    'process_description' => ':owner (started :started)',
    'unknown_owner' => 'unknown owner',
    'unknown' => 'unknown',
    'save' => 'Save',
    'apply' => 'Apply',
    'language' => 'Language',
    'loading' => 'Loading...',
    'manual' => [
        'documentation' => 'Documentation',
        'intro' => 'Operational guide for package users. Use the quick links to jump directly to the sections you need.',
        'sections' => 'Sections',
        'empty' => 'No sections available.',
        'not_found' => 'Manual file not found.',
    ],
    'browser' => [
        'invalid_response' => 'The server returned an invalid response. Please try again.',
        'request_failed' => 'The request failed. Please try again.',
        'translation' => 'Translation',
        'no_example' => 'No translation example is available.',
        'invalid_suggestion' => 'The server returned an invalid suggestion. Please try again.',
        'draft_kept' => 'Your draft was kept. Use the suggestion when ready.',
        'mark_read' => 'Mark as read',
        'notifications_failed' => 'Notifications could not be refreshed.',
        'close' => 'Close',
        'dismiss_notification' => 'Dismiss notification',
        'batch_cancelled' => 'Batch cancelled.',
        'batch_failed' => 'Batch finished with failures. Check notifications.',
        'batch_finished' => 'Batch finished. Reload to see changes.',
        'batch_refresh_failed' => 'Batch progress could not be refreshed.',
    ],
    'notifications' => 'Notifications',
    'mark_all_read' => 'Mark all as read',
    'batch_progress' => 'Batch progress',
    'app_name' => 'Interpresso for Laravel',
    'something_wrong' => 'Something went wrong, please contact site admin.',
    'queue_required' => "No background queue is configured, so this would run inside the web request and be cut off by PHP's time limit. Run: :command. To use this button without Supervisor, set QUEUE_CONNECTION=database, enable interpresso.schedule.queue_worker, and run php artisan schedule:run every minute via cron (recommended for shared hosting).",
    'queue_worker_required' => "This action requires a background queue to avoid PHP's web time limit. Configure a database or Redis queue connection, then run: :command. Without Supervisor, set QUEUE_CONNECTION=database, enable interpresso.schedule.queue_worker, and run php artisan schedule:run every minute via cron to use this button (recommended for shared hosting).",
    'reload_suggestion' => '<br><span class="text-red-500 text-xs">You may need to reload the page to see changes.</span>',
    'import' => [
        'start_message' => 'Process started. You will receive a message (bottom right) when finished.',
        'nothing_imported' => 'Nothing imported.',
        'processing_no_action' => 'A process is running in the background, no action allowed. Wait until the task finishes.'
    ],
    'jobs' => [
        'delete_success' => ':batches Batches deleted. :jobs Jobs deleted.',
        'delete_not_found' => 'Nothing deleted. No jobs found.'
    ]
];
