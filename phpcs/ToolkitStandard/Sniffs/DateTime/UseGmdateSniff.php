<?php
declare(strict_types=1);

namespace ToolkitStandard\Sniffs\DateTime;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class UseGmdateSniff implements Sniff
{
    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        if (strtolower($token['content']) !== 'date') {
            return;
        }

        // Ensure this is a function call and not method/static
        $next = $phpcsFile->findNext([T_WHITESPACE], $stackPtr + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }
        $prev = $phpcsFile->findPrevious([T_WHITESPACE], $stackPtr - 1, null, true);
        if ($prev !== false && in_array($tokens[$prev]['code'], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.',
            $stackPtr,
            'UseGmdate'
        );

        if ($fix === true) {
            $phpcsFile->fixer->beginChangeset();
            $phpcsFile->fixer->replaceToken($stackPtr, 'gmdate');
            $phpcsFile->fixer->endChangeset();
        }
    }
}