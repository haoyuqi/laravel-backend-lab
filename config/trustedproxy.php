<?php

return [
    // Only trust proxies controlled by this deployment, never arbitrary clients.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
