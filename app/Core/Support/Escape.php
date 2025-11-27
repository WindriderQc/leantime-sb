<?php

namespace Leantime\Core\Support;

/**
 * Escape
 *
 * Security helper for escaping output to prevent XSS attacks.
 * Provides context-aware escaping for different output contexts.
 *
 * @package Leantime\Core\Support
 */
class Escape
{
    /**
     * Escape HTML content
     *
     * @param string|null $value Value to escape
     * @param string $encoding Character encoding (default: UTF-8)
     * @return string Escaped HTML-safe string
     */
    public static function html(?string $value, string $encoding = 'UTF-8'): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, $encoding, true);
    }

    /**
     * Escape HTML attributes
     *
     * @param string|null $value Value to escape
     * @param string $encoding Character encoding (default: UTF-8)
     * @return string Escaped attribute-safe string
     */
    public static function attr(?string $value, string $encoding = 'UTF-8'): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, $encoding, true);
    }

    /**
     * Escape JavaScript string literals
     *
     * @param string|null $value Value to escape
     * @return string Escaped JavaScript-safe string
     */
    public static function js(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Escape for JavaScript context
        $value = str_replace(
            ['\\', "'", '"', "\n", "\r", "\t", '<', '>', '&'],
            ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t', '\\x3C', '\\x3E', '\\x26'],
            $value
        );

        return $value;
    }

    /**
     * Escape URL parameters
     *
     * @param string|null $value Value to escape
     * @return string URL-encoded string
     */
    public static function url(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return rawurlencode($value);
    }

    /**
     * Escape CSS values
     *
     * @param string|null $value Value to escape
     * @return string CSS-safe string
     */
    public static function css(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Only allow alphanumeric, space, and safe CSS characters
        return preg_replace('/[^a-zA-Z0-9\s\-_#,\.\(\)%]/', '', $value);
    }

    /**
     * Escape GET parameter value (alias for html)
     *
     * @param string|null $value Value to escape
     * @return string Escaped value
     */
    public static function get(?string $value): string
    {
        return self::html($value);
    }

    /**
     * Escape POST parameter value (alias for html)
     *
     * @param string|null $value Value to escape
     * @return string Escaped value
     */
    public static function post(?string $value): string
    {
        return self::html($value);
    }

    /**
     * Escape array of values
     *
     * @param array $values Array of values to escape
     * @param string $context Escape context (html, attr, js, url, css)
     * @return array Escaped values
     */
    public static function array(array $values, string $context = 'html'): array
    {
        $method = match ($context) {
            'attr' => 'attr',
            'js' => 'js',
            'url' => 'url',
            'css' => 'css',
            default => 'html',
        };

        return array_map(
            fn ($value) => is_string($value) ? self::$method($value) : $value,
            $values
        );
    }

    /**
     * Strip tags and escape HTML
     *
     * @param string|null $value Value to clean
     * @param string|null $allowedTags Allowed HTML tags (e.g., '<p><a>')
     * @return string Cleaned and escaped string
     */
    public static function stripTags(?string $value, ?string $allowedTags = null): string
    {
        if ($value === null) {
            return '';
        }

        $value = $allowedTags ? strip_tags($value, $allowedTags) : strip_tags($value);
        
        return self::html($value);
    }

    /**
     * Check if string contains potential XSS patterns
     *
     * @param string $value Value to check
     * @return bool True if suspicious patterns detected
     */
    public static function containsXSS(string $value): bool
    {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // Event handlers like onclick=, onload=, etc.
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/expression\s*\(/i', // CSS expression
            '/vbscript:/i',
            '/data:text\/html/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize SVG content to prevent XSS
     *
     * @param string $svgContent SVG XML content
     * @return string|false Sanitized SVG or false if invalid
     */
    public static function svg(string $svgContent)
    {
        // Block SVG files completely if they contain scripts
        if (self::containsXSS($svgContent)) {
            return false;
        }

        // Remove dangerous elements from SVG
        $dangerousElements = [
            'script',
            'foreignObject',
            'iframe',
            'object',
            'embed',
            'use', // Can reference external resources
        ];

        foreach ($dangerousElements as $element) {
            $svgContent = preg_replace('/<' . $element . '[^>]*>.*?<\/' . $element . '>/is', '', $svgContent);
            $svgContent = preg_replace('/<' . $element . '[^>]*\/>/is', '', $svgContent);
        }

        // Remove dangerous attributes
        $svgContent = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $svgContent);

        return $svgContent;
    }
}
