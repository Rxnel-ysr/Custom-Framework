<?php

return [
    // ======================================================================
    // | Application Router Configuration                                   |
    // ======================================================================
    'router' => 'RegexRouter',

    /*
    |--------------------------------------------------------------------------
    | API Route Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix is automatically prepended to all API routes.
    | For example, a route defined as `/users` will become `/api/users`.
    | Defaulted to '/api'.
    |
    */
    'api_prefix' => 'api',

    // ======================================================================
    // | Available Router Options                                           |
    // ======================================================================
    'choices' => [
        'Trie',          // Best for small apps (<10 routes), simple implementation
        'RadixTree',     // Optimized for medium/large route sets, memory efficient

        'RegexRouter',   // For complex route patterns with regular expressions 

        // TODO: Add router implementation
        'HybridRouter',  // Combines multiple strategies for optimal performance 

        // TODO: Add router implementation
        'GraphQLRouter', // Specialized for GraphQL endpoint routing 

        // TODO: Add router implementation
        'LayeredRouter'  // For microservices with route versioning/namespaces 

    ],
];
