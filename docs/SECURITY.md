# Security Improvements Documentation

This document describes the security enhancements added to the Leantime fork.

## Overview

Three security helper classes have been added to prevent common web vulnerabilities:

1. **SanitizeForLLM** - Prevents prompt injection attacks in AI/LLM integrations
2. **SanitizeFilename** - Prevents directory traversal and file upload vulnerabilities
3. **Escape** - Prevents XSS (Cross-Site Scripting) attacks with context-aware escaping

## Location

All security helpers are located in: `app/Core/Support/`

## Usage Examples

### SanitizeForLLM

Use when sending user input to LLM/AI APIs:

```php
use Leantime\Core\Support\SanitizeForLLM;

// Sanitize user input before sending to LLM
$userPrompt = $_POST['user_question'];
$safePrompt = SanitizeForLLM::sanitize($userPrompt);

// Send safe prompt to AI
$aiResponse = $aiService->query($safePrompt);

// Check if input contains suspicious patterns
if (SanitizeForLLM::containsSuspiciousPatterns($userInput)) {
    Log::warning('Potential prompt injection attempt detected');
}

// Sanitize array of inputs
$safeInputs = SanitizeForLLM::sanitizeArray($userInputs);
```

**What it prevents:**
- Prompt injection attacks ("ignore previous instructions and...")
- Role-switching attempts ("you are now a...")
- System prompt manipulation
- Attempts to access internal AI instructions

### SanitizeFilename

Use when handling file uploads:

```php
use Leantime\Core\Support\SanitizeFilename;

// Basic filename sanitization
$uploadedFile = $_FILES['document']['name'];
$safeFilename = SanitizeFilename::sanitize($uploadedFile);

// Sanitize with allowed extensions
$safeFilename = SanitizeFilename::sanitizeWithExtension(
    $uploadedFile,
    ['jpg', 'png', 'pdf', 'docx'],
    'txt' // default if extension not allowed
);

// Generate unique filename
$uniqueFilename = SanitizeFilename::generateUnique($uploadedFile, 'upload_');

// Check if filename is suspicious
if (SanitizeFilename::isSuspicious($filename)) {
    Log::warning('Suspicious filename detected: ' . $filename);
    throw new Exception('Invalid filename');
}
```

**What it prevents:**
- Directory traversal attacks (../../../etc/passwd)
- Null byte injection
- Executable file uploads (.php, .exe, etc.)
- Path manipulation
- Windows reserved filenames (CON, PRN, etc.)

### Escape

Use for output escaping in templates and views:

```php
use Leantime\Core\Support\Escape;

// In PHP templates (.tpl.php)
<div><?php echo Escape::html($userInput); ?></div>

// HTML attributes
<input type="text" value="<?php echo Escape::attr($value); ?>">

// JavaScript context
<script>
var userName = '<?php echo Escape::js($userName); ?>';
</script>

// URL parameters
<a href="?search=<?php echo Escape::url($searchTerm); ?>">Search</a>

// CSS values
<div style="color: <?php echo Escape::css($userColor); ?>">Text</div>

// GET/POST parameters (aliases)
<?php echo Escape::get($_GET['param']); ?>
<?php echo Escape::post($_POST['field']); ?>

// Strip tags and escape
<?php echo Escape::stripTags($html, '<p><a>'); ?>

// SVG sanitization
$safeSVG = Escape::svg($uploadedSVGContent);
if ($safeSVG === false) {
    // SVG contains dangerous content
    throw new Exception('Invalid SVG file');
}

// Check for XSS patterns
if (Escape::containsXSS($userInput)) {
    Log::warning('Potential XSS attempt detected');
}
```

**What it prevents:**
- XSS (Cross-Site Scripting) attacks
- Script injection in HTML
- JavaScript event handler injection
- SVG-based XSS attacks

## Integration with Existing Code

### Example: File Upload Controller

```php
use Leantime\Core\Support\SanitizeFilename;
use Leantime\Core\Support\Escape;
use Illuminate\Support\Facades\Log;

public function uploadFile()
{
    $uploadedFile = $_FILES['file'] ?? null;
    
    if (!$uploadedFile) {
        return response()->json(['error' => 'No file uploaded'], 400);
    }
    
    // Sanitize filename
    $originalName = $uploadedFile['name'];
    $safeFilename = SanitizeFilename::sanitizeWithExtension(
        $originalName,
        ['pdf', 'docx', 'xlsx', 'jpg', 'png']
    );
    
    // Check for suspicious patterns
    if (SanitizeFilename::isSuspicious($originalName)) {
        Log::warning('Suspicious file upload attempt', [
            'filename' => $originalName,
            'user_id' => auth()->id()
        ]);
        return response()->json(['error' => 'Invalid filename'], 400);
    }
    
    // Generate unique filename to prevent collisions
    $uniqueFilename = SanitizeFilename::generateUnique($safeFilename, 'upload_');
    
    // Save file
    $uploadPath = storage_path('uploads/' . $uniqueFilename);
    move_uploaded_file($uploadedFile['tmp_name'], $uploadPath);
    
    return response()->json([
        'success' => true,
        'filename' => Escape::html($uniqueFilename)
    ]);
}
```

### Example: AI Integration Service

```php
use Leantime\Core\Support\SanitizeForLLM;
use Illuminate\Support\Facades\Log;

public function queryAI(string $userPrompt): string
{
    // Sanitize user input
    $safePrompt = SanitizeForLLM::sanitize($userPrompt, 5000);
    
    // Log suspicious activity
    if (SanitizeForLLM::containsSuspiciousPatterns($userPrompt)) {
        Log::warning('Potential prompt injection detected', [
            'user_id' => auth()->id(),
            'prompt_preview' => substr($userPrompt, 0, 100)
        ]);
    }
    
    // Build safe system prompt
    $systemPrompt = "You are a helpful project management assistant.";
    
    // Query AI with sanitized input
    $response = $this->aiClient->chat([
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $safePrompt]
    ]);
    
    return $response;
}
```

### Example: Template Output (Blade)

```blade
{{-- Blade automatically escapes with {{ }} --}}
<div>{{ $userInput }}</div>

{{-- For unescaped output (use with extreme caution) --}}
<div>{!! Escape::html($trustedHTML) !!}</div>

{{-- URL parameters --}}
<a href="?id={{ Escape::url($itemId) }}">View Item</a>

{{-- JavaScript context --}}
<script>
var config = {
    userName: '{{ Escape::js($user->name) }}',
    userId: {{ $user->id }}
};
</script>
```

## Testing

Test files should be created for each security helper:

```bash
# Run security tests
docker exec -it leantime php vendor/bin/codecept run Unit Security
```

## Security Checklist

When implementing new features, always:

- [ ] Sanitize all file uploads with `SanitizeFilename`
- [ ] Escape all user input in templates with `Escape`
- [ ] Sanitize LLM prompts with `SanitizeForLLM`
- [ ] Validate file extensions on upload
- [ ] Log suspicious activity
- [ ] Use parameterized database queries
- [ ] Implement rate limiting on sensitive endpoints
- [ ] Review code for XSS vulnerabilities

## References

- **Source:** Based on security enhancements from ttracx/safe4work fork
- **OWASP:** https://owasp.org/www-project-top-ten/
- **Laravel Security:** https://laravel.com/docs/security

## Future Enhancements

Planned security improvements:

1. Content Security Policy (CSP) headers
2. Rate limiting middleware
3. CSRF token validation helpers
4. SQL injection detection patterns
5. File type validation (MIME type checking)
6. Image sanitization library integration
