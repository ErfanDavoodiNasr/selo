<?php

return [
    'paths' => [
        resource_path('views'),
    ],
    'compiled' => realpath(storage_path('framework/views')) ?: storage_path('framework/views'),
];
