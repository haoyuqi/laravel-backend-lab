<?php

$retentionDays = env('VISITOR_RETENTION_DAYS');

if ($retentionDays === null || (is_string($retentionDays) && trim($retentionDays) === '')) {
    $resolvedRetentionDays = 30;
} elseif (is_string($retentionDays)) {
    $trimmed = trim($retentionDays);
    $validated = filter_var($trimmed, FILTER_VALIDATE_INT);
    $resolvedRetentionDays = $validated !== false ? $validated : $trimmed;
} else {
    $resolvedRetentionDays = $retentionDays;
}

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

    'retention_days' => $resolvedRetentionDays,

];
