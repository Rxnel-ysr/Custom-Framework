<?php
$root = dirname(__DIR__, 1);

return [
    'Trie' => $root . '/App/Core/Routers/TrieRouter.php',
    'RadixTree' => $root . '/App/Core/Routers/RadixRouter.php',
    'RegexRouter' => $root . '/App/Core/Routers/RegexRouter.php',
    'CachedRouter' => $root . '/',
    'HybridRouter' => $root . '/',
    'GraphQLRouter' => $root . '/',
    'LayeredRouter' => $root . '/'
];
