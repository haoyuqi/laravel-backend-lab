<?php

$retentionDays = env('VISITOR_RETENTION_DAYS');

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

    'retention_days' => $retentionDays === null || (is_string($retentionDays) && trim($retentionDays) === '')
        ? 30
        : (int) $retentionDays,

];
