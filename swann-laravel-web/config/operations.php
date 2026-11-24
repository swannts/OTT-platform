<?php

return [
    'alerts' => [
        'mail_to' => env('OPERATIONS_ALERT_EMAIL'),
        'minimum_severity' => env('OPERATIONS_ALERT_MINIMUM_SEVERITY', 'high'),
    ],
];
