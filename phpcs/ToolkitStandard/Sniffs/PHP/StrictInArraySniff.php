<?php
declare(strict_types=1);

namespace ToolkitStandard\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class StrictInArraySniff implements Sniff
{
    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        if (strtolower($token['content']) !== 'in_array') {
            return;
        }

        // Ensure this is a function call (next non-whitespace must be open parenthesis)
        $next = $phpcsFile->findNext([T_WHITESPACE], $stackPtr + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        // Ignore method or static calls like $obj->in_array() or Class::in_array()
        $prev = $phpcsFile->findPrevious([T_WHITESPACE], $stackPtr - 1, null, true);
        if ($prev !== false && in_array($tokens[$prev]['code'], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            return;
        }

        $open = $next;
        $close = $tokens[$open]['parenthesis_closer'] ?? null;
        if ($close === null) {
            return;
        }

        // Count top-level commas to determine number of arguments
        $commaCount = 0;
        $depth = 0;
        for ($i = $open + 1; $i < $close; $i++) {
            $code = $tokens[$i]['code'];
            if (in_array($code, [T_OPEN_PARENTHESIS, T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET], true)) {
                $depth++;
            } elseif (in_array($code, [T_CLOSE_PARENTHESIS, T_CLOSE_SHORT_ARRAY, T_CLOSE_SQUARE_BRACKET], true)) {
                $depth--;
            } elseif ($code === T_COMMA && $depth === 0) {
                $commaCount++;
            }
        }

        // If already has 3+ args, do nothing
        if ($commaCount >= 2) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Not using strict comparison for in_array; supply true for $strict argument',
            $stackPtr,
            'MissingStrict'
        );

        if ($fix === true) {
            $phpcsFile->fixer->beginChangeset();
            $insertAt = $close; // before closing parenthesis
            $beforeClose = $phpcsFile->findPrevious([T_WHITESPACE], $close - 1, $open + 1, false);
            if ($beforeClose !== false && $tokens[$beforeClose]['content'] === ',') {
                // Trailing comma style is unusual for calls; just append true
                $phpcsFile->fixer->addContentBefore($close, ' true');
            } else {
                $phpcsFile->fixer->addContentBefore($close, ', true');
            }
            $phpcsFile->fixer->endChangeset();
        }
    }
}