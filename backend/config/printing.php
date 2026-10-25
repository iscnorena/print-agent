<?php

return [

    /*
    | Storage disk for original files and normalized PDFs.
    */
    'disk' => env('PRINTING_DISK', 'local'),

    /*
    | Days a job may wait in "ready" before it expires and its files are
    | deleted. A location can override it with ready_expiration_days.
    */
    'ready_expiration_days' => (int) env('PRINTING_READY_EXPIRATION_DAYS', 7),

    /*
    | Largest PDF the operator page accepts, in kilobytes. PHP's
    | upload_max_filesize and post_max_size must be at least this big.
    */
    'max_upload_kb' => (int) env('PRINTING_MAX_UPLOAD_KB', 20480),

    /*
    | A printer whose agent has not sent a heartbeat for this many seconds is
    | shown as offline. The agent sends one every 60 seconds.
    */
    'agent_offline_after_seconds' => (int) env('PRINTING_AGENT_OFFLINE_AFTER_SECONDS', 150),

    /*
    | Timezone used to show dates to operators. The database stays in UTC.
    */
    'display_timezone' => env('PRINTING_DISPLAY_TIMEZONE', 'America/Mexico_City'),

];
