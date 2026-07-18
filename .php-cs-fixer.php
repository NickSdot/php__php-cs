<?php

declare(strict_types=1);

$rules = [

    // Section: Sets
    '@PER' => true,
    '@PHP85Migration' => true,

    // Section: Imports
    'global_namespace_import' => [
        'import_classes' => false,
        'import_constants' => false,
        'import_functions' => true,
    ],
    'native_function_invocation' => [
        'include' => ['@internal'],
        'scope' => 'all',
        'strict' => true,
    ],
    'ordered_imports' => [
        'case_sensitive' => true,
        'imports_order' => [
            'class',
            'function',
            'const',
        ],
    ],
    'no_unused_imports' => true,

    // Types:
    'ordered_types' => [
        'case_sensitive' => true,
        'sort_algorithm' => 'none',
        'null_adjustment' => 'always_last',
    ],

    // Section:
    'no_redundant_readonly_property' => true,
    //'assign_null_coalescing_to_coalesce_equal' => true,
    //'ternary_to_null_coalescing' => true,
    //'unary_operator_spaces' => true,
    //'long_to_shorthand_operator' => true,
    'strict_comparison' => true,
    'declare_strict_types' => true,
    'strict_param' => true,
    'yoda_style' => true,

    // Section:
    'array_push' => true,
    'mb_str_functions' => true,
    'no_multiline_whitespace_around_double_arrow' => true,
    'whitespace_after_comma_in_array' => true,
    'modernize_types_casting' => true,
    'explicit_string_variable' => false,

    'no_alternative_syntax' => true,
    'final_class' => true,


    // Section: Docs
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'phpdoc_line_span' => [
        'const' => 'single',
        'method' => 'single',
        'property' => 'single',
    ],

];

$finder = new PhpCsFixer\Finder()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'Fixtures',
    ])
;

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
;
