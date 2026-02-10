<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\ForbiddenFunctionsSniff;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer;
use PhpCsFixer\Fixer\ClassNotation\NoNullPropertyInitializationFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationArrayAssignmentFixer;
use PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationBracesFixer;
use PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationIndentationFixer;
use PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationSpacesFixer;
use PhpCsFixer\Fixer\FunctionNotation\SingleLineThrowFixer;
use PhpCsFixer\Fixer\ListNotation\ListSyntaxFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocTagRenameFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocInlineTagNormalizerFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use SlevomatCodingStandard\Sniffs\Classes\ModernClassNameReferenceSniff;
use SlevomatCodingStandard\Sniffs\Classes\ParentCallSpacingSniff;
use SlevomatCodingStandard\Sniffs\Classes\PropertySpacingSniff;
use SlevomatCodingStandard\Sniffs\Commenting\UselessInheritDocCommentSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\RequireNullCoalesceOperatorSniff;
use SlevomatCodingStandard\Sniffs\Functions\ArrowFunctionDeclarationSniff;
use SlevomatCodingStandard\Sniffs\Functions\StrictCallSniff;
use SlevomatCodingStandard\Sniffs\Functions\UnusedInheritedVariablePassedToClosureSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\AlphabeticallySortedUsesSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UnusedUsesSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UseDoesNotStartWithBackslashSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UseFromSameNamespaceSniff;
use SlevomatCodingStandard\Sniffs\Numbers\RequireNumericLiteralSeparatorSniff;
use SlevomatCodingStandard\Sniffs\PHP\UselessParenthesesSniff;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayListItemNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPreparedSets(psr12: true, common: true, cleanCode: true)
    ->withParallel()
    ->withCache(directory: __DIR__ . '/var/ecs_cache')
    ->withPaths(paths: [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        ArrayListItemNewlineFixer::class => null,
        PhpdocToCommentFixer::class => null,
        PhpdocAlignFixer::class => null,
        ClassAttributesSeparationFixer::class => null,
        PhpdocInlineTagNormalizerFixer::class => null,
        GeneralPhpdocTagRenameFixer::class => null,
        SingleLineThrowFixer::class => null,
        ArrayOpenerAndCloserNewlineFixer::class => null,
        ParentCallSpacingSniff::class => null,
        DoctrineAnnotationBracesFixer::class => null,
        NotOperatorWithSuccessorSpaceFixer::class => null,
        UselessParenthesesSniff::class => null,
        MethodChainingIndentationFixer::class => ['src/DependencyInjection/*Configuration.php'],
        'SlevomatCodingStandard\Sniffs\Classes\UnusedPrivateElementsSniff.WriteOnlyProperty' => ['src/Entity/User.php'],
        'SlevomatCodingStandard\Sniffs\Whitespaces\DuplicateSpacesSniff.DuplicateSpaces' => null,
        'SlevomatCodingStandard\Sniffs\Commenting\DisallowCommentAfterCodeSniff.DisallowedCommentAfterCode' => null,
    ])
    ->withConfiguredRule(NoSuperfluousPhpdocTagsFixer::class, ['remove_inheritdoc' => false, 'allow_mixed' => false])
    ->withConfiguredRule(ClassDefinitionFixer::class, ['multi_line_extends_each_single_line' => false])
    ->withConfiguredRule(ClassAttributesSeparationFixer::class, ['elements' => ['method', 'property']])
    ->withConfiguredRule(ArraySyntaxFixer::class, ['syntax' => 'short'])
    ->withConfiguredRule(ListSyntaxFixer::class, ['syntax' => 'short'])
    ->withConfiguredRule(PropertySpacingSniff::class, ['minLinesCountBeforeWithComment' => 1, 'maxLinesCountBeforeWithComment' => 1, 'maxLinesCountBeforeWithoutComment' => 0])
    ->withConfiguredRule(UnusedUsesSniff::class, ['searchAnnotations' => true])
    ->withConfiguredRule(YodaStyleFixer::class, ['identical' => true])
    ->withConfiguredRule(ForbiddenFunctionsSniff::class, ['forbiddenFunctions' => [
        'chop' => 'rtrim',
        'close' => 'closedir',
        'delete' => 'unset',
        'doubleval' => 'floatval',
        'fputs' => 'fwrite',
        'imap_create' => 'createmailbox',
        'imap_fetchtext' => 'body',
        'imap_header' => 'headerinfo',
        'imap_listmailbox' => 'list',
        'imap_listsubscribed' => 'lsub',
        'imap_rename' => 'renamemailbox',
        'imap_scan' => 'listscan',
        'imap_scanmailbox' => 'listscan',
        'mt_rand' => 'random_int',
        'ini_alter' => 'set',
        'is_double' => 'is_float',
        'is_integer' => 'is_int',
        'is_null' => '!== null',
        'is_real' => 'is_float',
        'is_writeable' => 'is_writable',
        'join' => 'implode',
        'key_exists' => 'array_key_exists',
        'magic_quotes_runtime' => 'set_magic_quotes_runtime',
        'pos' => 'current',
        'rand' => 'random_int',
        'show_source' => 'file',
        'sizeof' => 'count',
        'strchr' => 'strstr',
        'create_function' => null,
        'call_user_func' => null,
        'call_user_func_array' => null,
        'forward_static_call' => null,
        'forward_static_call_array' => null,
        'dump' => null,
        'die' => null,
        'dd' => null,
        'echo' => null,
        'var_dump' => null,
    ]])
    ->withRules([
        DeclareStrictTypesFixer::class,
        NoNullPropertyInitializationFixer::class,
        ArrowFunctionDeclarationSniff::class,
        StrictCallSniff::class,
        UseDoesNotStartWithBackslashSniff::class,
        AlphabeticallySortedUsesSniff::class,
        RequireNumericLiteralSeparatorSniff::class,
        UselessParenthesesSniff::class,
        RequireNullCoalesceOperatorSniff::class,
        ModernClassNameReferenceSniff::class,
        UselessInheritDocCommentSniff::class,
        UseFromSameNamespaceSniff::class,
        UnusedInheritedVariablePassedToClosureSniff::class,
        DoctrineAnnotationArrayAssignmentFixer::class,
        DoctrineAnnotationIndentationFixer::class,
        DoctrineAnnotationSpacesFixer::class,
    ])
;
