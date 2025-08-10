<?php
declare(strict_types=1);

namespace ToolkitStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class FunctionThrowsTagSniff implements Sniff
{
    public function register(): array
    {
        return [T_FUNCTION, T_CLOSURE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Find function body range
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;
        if ($opener === null || $closer === null) {
            // Probably an arrow function or abstract; skip
            return;
        }

        $hasThrow = $phpcsFile->findNext(T_THROW, $opener + 1, $closer) !== false;
        if (!$hasThrow) {
            return;
        }

        // Find immediate preceding docblock
        $prev = $phpcsFile->findPrevious([T_WHITESPACE, T_ATTRIBUTE_END, T_ATTRIBUTE], $stackPtr - 1, null, true);
        if ($prev === false || $tokens[$prev]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
            // No docblock right above; do not create a new one here to avoid conflicts
            return;
        }
        $docClose = $prev;
        $docOpen = $tokens[$docClose]['comment_opener'];

        // Check if @throws is already present
        $hasThrowsTag = false;
        $i = $docOpen;
        while ($i <= $docClose) {
            if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG && strtolower($tokens[$i]['content']) === '@throws') {
                $hasThrowsTag = true;
                break;
            }
            $i++;
        }
        if ($hasThrowsTag) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Missing @throws tag in function comment',
            $stackPtr,
            'MissingThrowsTag'
        );

        if ($fix === true) {
            $indent = '';
            // Find indentation from the docblock
            $lineStartPtr = $docOpen - 1;
            while ($lineStartPtr > 0 && $tokens[$lineStartPtr]['line'] === $tokens[$docOpen]['line']) {
                $lineStartPtr--;
            }
            $lineStartPtr++;
            if ($tokens[$lineStartPtr]['code'] === T_WHITESPACE) {
                $indent = $tokens[$lineStartPtr]['content'];
            }

            $phpcsFile->fixer->beginChangeset();
            // Insert before the closing */
            $insert = $indent . ' * @throws \\Throwable' . $phpcsFile->eolChar;
            $phpcsFile->fixer->addContentBefore($docClose, $insert);
            $phpcsFile->fixer->endChangeset();
        }
    }
}