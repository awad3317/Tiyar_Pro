<?php

return [
    'base_url'        => env('WHATSAPP_BASE_URL', 'http://195.35.24.73:4000'),
    'global_api_key'  => env('WHATSAPP_GLOBAL_API_KEY', env('WHATSAPP_SECRET_TOKEN', '')),
    'instance_id'     => env('WHATSAPP_INSTANCE_ID', null),
    'webhook_url'     => env('WHATSAPP_WEBHOOK_URL', null),
    'timeout'         => (int) env('WHATSAPP_TIMEOUT', 30),
];
