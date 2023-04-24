<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())->in(__DIR__.'/src');

return (new PhpCsFixer\Config())->setRules(
    [
        '@Symfony'               => true,
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '='  => 'align_single_space_minimal',
            ],
        ],
        'phpdoc_to_comment' => false,
        'header_comment'    => [
            'header' => '',
        ],
    ]
)->setFinder($finder);
