<?php

return [
    // ======================================================================
    // | Application Router Configuration                                   |
    // ======================================================================
    'router' => 'RadixTree',

    // ======================================================================
    // | Available Router Options                                           |
    // ======================================================================
    'choices' => [
        'Trie',          // Best for small apps (<10 routes), simple implementation
        'RadixTree',     // Optimized for medium/large route sets, memory efficient
        'RegexRouter',   // For complex route patterns with regular expressions (SOON)
        'CachedRouter',  // For production environments (caches compiled routes) (SOON)
        'HybridRouter',  // Combines multiple strategies for optimal performance (SOON)
        'GraphQLRouter', // Specialized for GraphQL endpoint routing (SOON)
        'LayeredRouter'  // For microservices with route versioning/namespaces (SOON)
    ],
];
