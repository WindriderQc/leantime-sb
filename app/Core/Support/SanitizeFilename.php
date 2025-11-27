<?php

namespace Leantime\Core\Support;

/**
 * SanitizeFilename
 *
 * Sanitizes filenames to prevent directory traversal attacks and ensure safe file handling.
 * Removes dangerous characters and path manipulation attempts.
 *
 * @package Leantime\Core\Support
 */
class SanitizeFilename
{
    /**
     * Sanitize a filename for safe file system operations
     *
     * @param string $filename Original filename
     * @param string $defaultName Fallback name if sanitization results in empty string
     * @return string Sanitized filename
     */
    public static function sanitize(string $filename, string $defaultName = 'file'): string
    {
        // Remove any path components (directory traversal prevention)
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Remove directory traversal patterns
        $filename = str_replace(['../', '..\\', '../', '..\\'], '', $filename);

        // Remove control characters and other dangerous characters
        $filename = preg_replace('/[<>:"|?*\x00-\x1F\x7F]/', '', $filename);

        // Remove leading/trailing dots and spaces
        $filename = trim($filename, '. ');

        // If filename is empty after sanitization, use default
        if (empty($filename)) {
            $filename = $defaultName;
        }

        // Limit filename length (255 is typical filesystem limit)
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            
            $maxNameLength = 255 - strlen($extension) - 1; // -1 for the dot
            $filename = substr($nameWithoutExt, 0, $maxNameLength) . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Sanitize filename and ensure it has a safe extension
     *
     * @param string $filename Original filename
     * @param array $allowedExtensions List of allowed extensions (without dot)
     * @param string $defaultExtension Default extension if none provided or not allowed
     * @return string Sanitized filename with safe extension
     */
    public static function sanitizeWithExtension(
        string $filename,
        array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
        string $defaultExtension = 'txt'
    ): string {
        $filename = self::sanitize($filename);
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Check if extension is allowed
        if (!in_array($extension, $allowedExtensions)) {
            $extension = $defaultExtension;
        }

        return $nameWithoutExt . '.' . $extension;
    }

    /**
     * Check if filename contains suspicious patterns
     *
     * @param string $filename Filename to check
     * @return bool True if suspicious patterns detected
     */
    public static function isSuspicious(string $filename): bool
    {
        $suspiciousPatterns = [
            // Directory traversal
            '/\.\.[\\/]/',
            // Null bytes
            '/\x00/',
            // Executable extensions (adjust based on your security requirements)
            '/\.(php|phtml|php3|php4|php5|php7|phps|exe|bat|cmd|com|pif|scr|vbs|js)$/i',
            // Hidden files on Unix
            '/^\./',
            // Windows reserved names
            '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\.|$)/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a unique safe filename
     *
     * @param string $originalFilename Original filename
     * @param string $prefix Optional prefix to add
     * @return string Unique sanitized filename
     */
    public static function generateUnique(string $originalFilename, string $prefix = ''): string
    {
        $sanitized = self::sanitize($originalFilename);
        $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($sanitized, PATHINFO_FILENAME);

        $uniqueId = uniqid($prefix, true);
        
        if (!empty($extension)) {
            return $uniqueId . '_' . $nameWithoutExt . '.' . $extension;
        }
        
        return $uniqueId . '_' . $nameWithoutExt;
    }
}
