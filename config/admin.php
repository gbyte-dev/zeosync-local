<?php

return [
    // Comma-separated list of IPs allowed to access admin routes.
    // Leave empty to allow any IP (still requires admin auth).
    'allowed_ips' => env('ADMIN_ALLOWED_IPS', ''),
];
