<?php
declare(strict_types=1);

namespace ToolkitStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class FilePackageTagSniff implements Sniff
{
    /**
     * Package name to insert when missing.
     * @var string
     */
    public $packageName = 'project';

    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        // Only run once per file at the first open tag
        if ($stackPtr !== 0 && $phpcsFile->getTokens()[$stackPtr - 1]['code'] === T_OPEN_TAG) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext([T_WHITESPACE], $stackPtr + 1, null, true);

        // If next significant token is a doc comment, treat as file header
        if ($next !== false && $tokens[$next]['code'] === T_DOC_COMMENT_OPEN_TAG) {
            $docOpen = $next;
            $docClose = $tokens[$docOpen]['comment_closer'];
            // Search for @package tag
            $hasPackage = false;
            for ($i = $docOpen; $i <= $docClose; $i++) {
                if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG && strtolower($tokens[$i]['content']) === '@package') {
                    $hasPackage = true;
                    break;
                }
            }
            if ($hasPackage) {
                return;
            }

            $fix = $phpcsFile->addFixableError(
                'Missing @package tag in file comment',
                $docOpen,
                'MissingPackageTag'
            );

            if ($fix === true) {
                // Determine indentation from the docblock
                $indent = '';
                $lineStartPtr = $docOpen - 1;
                while ($lineStartPtr > 0 && $tokens[$lineStartPtr]['line'] === $tokens[$docOpen]['line']) {
                    $lineStartPtr--;
                }
                $lineStartPtr++;
                if ($tokens[$lineStartPtr]['code'] === T_WHITESPACE) {
                    $indent = $tokens[$lineStartPtr]['content'];
                }

                $phpcsFile->fixer->beginChangeset();
                $insert = $indent . ' * @package ' . $this->packageName . $phpcsFile->eolChar;
                $phpcsFile->fixer->addContentBefore($docClose, $insert);
                $phpcsFile->fixer->endChangeset();
            }
            return;
        }

        // No file header found; create a minimal one after the open tag
        $fix = $phpcsFile->addFixableError(
            'Missing @package tag in file comment',
            $stackPtr,
            'MissingPackageTagHeader'
        );

        if ($fix === true) {
            $eol = $phpcsFile->eolChar;
            $indent = '';
            // Determine indentation of next token line
            if ($next !== false && $tokens[$next]['column'] > 1) {
                $indent = str_repeat(' ', $tokens[$next]['column'] - 1);
            }
            $phpcsFile->fixer->beginChangeset();
            $header = '/**' . $eol
                . ' * ' . $eol
                . ' * @package ' . $this->packageName . $eol
                . ' */' . $eol;
            $phpcsFile->fixer->addContent($stackPtr, $header);
            $phpcsFile->fixer->endChangeset();
        }
    }
}