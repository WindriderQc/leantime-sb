<?php

namespace Leantime\Core\Support;

/**
 * SanitizeForLLM
 *
 * Sanitizes user input to prevent prompt injection attacks when sending data to LLMs.
 * This class helps protect against malicious prompts that could manipulate AI behavior.
 *
 * @package Leantime\Core\Support
 */
class SanitizeForLLM
{
    /**
     * Sanitize text for safe use in LLM prompts
     *
     * @param string $text Input text to sanitize
     * @param int $maxLength Maximum allowed length (default: 10000)
     * @return string Sanitized text safe for LLM consumption
     */
    public static function sanitize(string $text, int $maxLength = 10000): string
    {
        // Trim whitespace
        $text = trim($text);

        // Truncate to maximum length
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
        }

        // Remove potential prompt injection patterns
        $patterns = [
            // Remove instruction-like patterns
            '/\b(ignore|disregard|forget)\s+(previous|all|above|prior)\s+(instructions|prompts|rules)\b/i',
            // Remove role-switching attempts
            '/\b(you\s+are\s+now|act\s+as|pretend\s+to\s+be|roleplay\s+as)\b/i',
            // Remove system prompt manipulation attempts
            '/\b(system\s*:|assistant\s*:|user\s*:)/i',
            // Remove attempts to access internal state
            '/\b(show|reveal|display)\s+(your|the)\s+(prompt|instructions|rules|system)\b/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return trim($text);
    }

    /**
     * Sanitize array of texts
     *
     * @param array $texts Array of text strings to sanitize
     * @param int $maxLength Maximum allowed length per text
     * @return array Sanitized texts
     */
    public static function sanitizeArray(array $texts, int $maxLength = 10000): array
    {
        return array_map(
            fn ($text) => is_string($text) ? self::sanitize($text, $maxLength) : $text,
            $texts
        );
    }

    /**
     * Check if text contains potential injection patterns
     *
     * @param string $text Text to check
     * @return bool True if suspicious patterns detected
     */
    public static function containsSuspiciousPatterns(string $text): bool
    {
        $suspiciousPatterns = [
            '/\b(ignore|disregard|forget)\s+(previous|all|above|prior)\s+(instructions|prompts|rules)\b/i',
            '/\b(you\s+are\s+now|act\s+as|pretend\s+to\s+be|roleplay\s+as)\b/i',
            '/\b(system\s*:|assistant\s*:|user\s*:)/i',
            '/\b(show|reveal|display)\s+(your|the)\s+(prompt|instructions|rules|system)\b/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
