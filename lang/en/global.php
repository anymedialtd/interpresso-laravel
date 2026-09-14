<?php

return [
    'app_name' => 'Interpresso for Laravel',
    'something_wrong' => 'Something went wrong, please contact site admin.',
    'queue_required' => "No queue worker is configured, so this would run inside the web request and be cut off by PHP's time limit. Run: :command",
    'queue_worker_required' => "This action requires a background queue to avoid PHP's web time limit. Configure a database or Redis queue connection, then run: :command",
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
