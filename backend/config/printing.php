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

];
