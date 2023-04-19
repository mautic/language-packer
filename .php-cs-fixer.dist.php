<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())->in(__DIR__.'/src');

return (new PhpCsFixer\Config())->setRules(
    [
        '@Symfony'               => true,
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align',
                '='  => 'align',
            ],
        ],
        'phpdoc_to_comment'      => false,
        'ordered_imports'        => true,
        'array_syntax'           => [
            'syntax' => 'short',
        ],
        'no_unused_imports'      => true,
        'header_comment'         => [
            'header' => '',
        ],
    ]
)->setFinder($finder);
