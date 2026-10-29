<?php

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

if ($basePath && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    $query = '';

    if (($queryPosition = strpos($uri, '?')) !== false) {
        $query = substr($uri, $queryPosition);
        $uri = substr($uri, 0, $queryPosition);
    }

    if ($uri === $basePath || str_starts_with($uri, $basePath.'/')) {
        $_SERVER['REQUEST_URI'] = substr($uri, strlen($basePath)) ?: '/';
        $_SERVER['REQUEST_URI'] .= $query;
    }
}

require __DIR__.'/public/index.php';
