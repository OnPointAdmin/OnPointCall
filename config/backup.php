<?php

return [
    'disk' => env('BACKUP_DISK', 'b2'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
];
