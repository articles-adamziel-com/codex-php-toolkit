<?php
declare(strict_types=1);

namespace ToolkitStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class InlineCommentPunctuationSniff implements Sniff
{
    public function register(): array
    {
        return [T_COMMENT];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];
        $content = $token['content'];

        // Only handle true inline comments starting with // or #
        if (!(strpos($content, '//') === 0 || strpos($content, '#') === 0)) {
            return;
        }

        $text = trim(ltrim($content, '/#'));
        if ($text === '') {
            return;
        }

        // Skip directive-like comments
        if (stripos($text, 'phpcs:') === 0 || stripos($text, '@codingstandards') !== false) {
            return;
        }

        $lastChar = substr($text, -1);
        if (in_array($lastChar, ['.', '!', '?'], true)) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Inline comments must end in full-stops, exclamation marks, or question marks',
            $stackPtr,
            'MissingPunctuation'
        );

        if ($fix === true) {
            $phpcsFile->fixer->beginChangeset();
            // Preserve trailing whitespace if any
            $trimmedRight = rtrim($content);
            $trailing = substr($content, strlen($trimmedRight));
            $phpcsFile->fixer->replaceToken($stackPtr, $trimmedRight . '.' . $trailing);
            $phpcsFile->fixer->endChangeset();
        }
    }
}