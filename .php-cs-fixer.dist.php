<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/bin')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/public')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
    ->exclude([
        'migrations',
        'scripts',
        'var',
    ]);

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'function_declaration' => [
            'closure_fn_spacing' => 'none', // Removes space after fn keyword
        ],
        'ternary_operator_spaces' => true,
    ])
    ->setFinder($finder);
