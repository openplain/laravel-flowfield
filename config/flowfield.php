<?php

return [
    'cache' => [
        'store' => env('FLOWFIELD_CACHE_STORE', null),  // null = default cache store
        'prefix' => 'flowfield',
        'ttl' => null,  // Forever — invalidation is event-driven, not time-based
    ],

    'auto_warm' => false,   // If true, immediately recalculate after invalidation

    'tag_based' => true,    // Use cache tags when available (better invalidation)
];
