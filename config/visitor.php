<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visitor Data Retention
    |--------------------------------------------------------------------------
    |
    | Raw visitor rows and request paths are personal data. The scheduled
    | `visitor-data:prune` command removes rows older than this window. Keep it
    | aligned with the privacy policy for the deployment.
    |
    */

    'retention_days' => (int) env('VISITOR_RETENTION_DAYS', 30),

];
