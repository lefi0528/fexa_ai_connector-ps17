<?php
/**
 * Copyright (c) 2025 Fexa AI
 *
 * All Rights Reserved.
 *
 * This module is proprietary software owned by Fexa AI.
 *
 * @author    Fexa AI <support@fexaai.com>
 * @copyright 2025 Fexa AI
 * @license   Proprietary
 */

/*
 * PHP-CS-Fixer config — EXACT PrestaShop coding standard (mirrors
 * PrestaShop\CodingStandards\CsFixer\Config from prestashop/php-dev-tools, which is what the
 * PrestaShop validator checks "Standards" against) PLUS the Fexa header_comment (for "Licenses").
 *
 * IMPORTANT: do NOT add global_namespace_import:import_classes=true, custom ordered_imports, etc.
 * The PrestaShop standard (@Symfony) wants global classes FULLY-QUALIFIED (\Context, \Category) —
 * importing them is what made a previous run explode the Standards count.
 *
 * Run:  vendor/bin/php-cs-fixer fix   (or:  php php-cs-fixer.phar fix)
 */

$header = <<<'EOF'
Copyright (c) 2025 Fexa AI

All Rights Reserved.

This module is proprietary software owned by Fexa AI.

@author    Fexa AI <support@fexaai.com>
@copyright 2025 Fexa AI
@license   Proprietary
EOF;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'node_modules', 'views/.vite'])
    ->notName('*.min.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        // PHP 7.4 target: NEVER add a trailing comma to a multiline parameter list
        // (that is PHP 8.0+ syntax and would fatal on 7.4). Arrays + call-arguments only.
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments']],
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'single'],
        'error_suppression' => [
            'mute_deprecation_error' => false,
            'noise_remaining_usages' => false,
            'noise_remaining_usages_exclude' => [],
        ],
        'function_to_constant' => false,
        'visibility_required' => ['elements' => ['property', 'method']],
        'no_alias_functions' => false,
        'phpdoc_summary' => false,
        'phpdoc_align' => ['align' => 'left'],
        'protected_to_private' => false,
        'psr_autoloading' => false,
        'self_accessor' => false,
        'yoda_style' => false,
        'non_printable_character' => true,
        'no_superfluous_phpdoc_tags' => false,
        // Header must sit FLUSH against <?php (Licenses: "no blank lines before the file comment").
        // @Symfony's default would insert a blank line after the opening tag — disable it.
        'blank_line_after_opening_tag' => false,
        // Fexa proprietary header (satisfies "Licenses": @author + no blank line before the comment).
        // separate:none so php-cs-fixer's namespace/phpdoc spacing rules decide what follows (one
        // blank before `namespace`, none before plain code in index.php).
        'header_comment' => [
            'header' => $header,
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'none',
        ],
    ])
    ->setFinder($finder);
