<?php

namespace Shared\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Template Manager
 * 
 * Centralized template management system for notifications with support for:
 * - Multi-channel templates (Email, SMS, WhatsApp, Telegram, Push)
 * - Multi-language support (en, ar, fr)
 * - Template inheritance and fallback
 * - Caching for performance
 * - Service-specific customization
 */
class TemplateManager
{
    /**
     * Supported languages
     */
    const SUPPORTED_LANGUAGES = ['en', 'ar', 'fr'];
    
    /**
     * Supported channels
     */
    const SUPPORTED_CHANNELS = ['email', 'sms', 'whatsapp', 'telegram', 'push'];
    
    /**
     * Template base path
     */
    private string $basePath;
    
    /**
     * Cache TTL in seconds (1 hour)
     */
    private int $cacheTtl = 3600;
    
    /**
     * Default language
     */
    private string $defaultLanguage = 'en';
    
    public function __construct()
    {
        $this->basePath = base_path('services/shared/src/Templates/Notifications');
    }
    
    /**
     * Get template content with fallback support
     *
     * @param string $template Template name (e.g., 'order.created')
     * @param string $channel Channel type (email, sms, etc.)
     * @param string $language Language code (en, ar, fr)
     * @param string|null $service Service name for service-specific templates
     * @return string Template content
     * @throws Exception When template not found
     */
    public function getTemplate(string $template, string $channel, string $language = 'en', ?string $service = null): string
    {
        // Generate cache key
        $cacheKey = $this->generateCacheKey($template, $channel, $language, $service);
        
        // Try to get from cache first
        $cachedTemplate = Cache::get($cacheKey);
        if ($cachedTemplate !== null) {
            return $cachedTemplate;
        }
        
        // Load template with fallback logic
        $templateContent = $this->loadTemplateWithFallback($template, $channel, $language, $service);
        
        // Cache the result
        Cache::put($cacheKey, $templateContent, $this->cacheTtl);
        
        return $templateContent;
    }
    
    /**
     * Process template with data substitution
     *
     * @param string $template Template name
     * @param string $channel Channel type
     * @param array $data Data for variable substitution
     * @param string $language Language code
     * @param string|null $service Service name
     * @return string Processed template content
     */
    public function processTemplate(string $template, string $channel, array $data = [], string $language = 'en', ?string $service = null): string
    {
        try {
            // Get template content
            $templateContent = $this->getTemplate($template, $channel, $language, $service);
            
            // Process variables
            $processedContent = $this->substituteVariables($templateContent, $data);
            
            // Apply channel-specific formatting
            $processedContent = $this->applyChannelFormatting($processedContent, $channel);
            
            return $processedContent;
            
        } catch (Exception $e) {
            Log::error('Template processing failed', [
                'template' => $template,
                'channel' => $channel,
                'language' => $language,
                'service' => $service,
                'error' => $e->getMessage()
            ]);
            
            // Return fallback template
            return $this->getFallbackTemplate($template, $channel, $data);
        }
    }
    
    /**
     * Load template with fallback logic
     *
     * @param string $template Template name
     * @param string $channel Channel type
     * @param string $language Language code
     * @param string|null $service Service name
     * @return string Template content
     * @throws Exception When no template found
     */
    private function loadTemplateWithFallback(string $template, string $channel, string $language, ?string $service): string
    {
        $fallbackPaths = $this->generateFallbackPaths($template, $channel, $language, $service);
        
        foreach ($fallbackPaths as $path) {
            if (File::exists($path)) {
                $content = File::get($path);
                
                Log::debug('Template loaded', [
                    'template' => $template,
                    'channel' => $channel,
                    'language' => $language,
                    'service' => $service,
                    'path' => $path
                ]);
                
                return $content;
            }
        }
        
        throw new Exception("Template not found: {$template} for channel {$channel} and language {$language}");
    }
    
    /**
     * Generate fallback paths in priority order
     *
     * @param string $template Template name
     * @param string $channel Channel type
     * @param string $language Language code
     * @param string|null $service Service name
     * @return array Array of file paths to try
     */
    private function generateFallbackPaths(string $template, string $channel, string $language, ?string $service): array
    {
        $paths = [];
        $templateFile = str_replace('.', '/', $template) . '.txt';
        
        // Priority 1: Service-specific template in requested language
        if ($service) {
            $paths[] = "{$this->basePath}/{$language}/{$service}/{$channel}/{$templateFile}";
        }
        
        // Priority 2: General template in requested language
        $paths[] = "{$this->basePath}/{$language}/general/{$channel}/{$templateFile}";
        
        // Priority 3: Base template in requested language
        $paths[] = "{$this->basePath}/{$language}/base/{$channel}/{$templateFile}";
        
        // Priority 4: Service-specific template in default language (if different)
        if ($language !== $this->defaultLanguage && $service) {
            $paths[] = "{$this->basePath}/{$this->defaultLanguage}/{$service}/{$channel}/{$templateFile}";
        }
        
        // Priority 5: General template in default language (if different)
        if ($language !== $this->defaultLanguage) {
            $paths[] = "{$this->basePath}/{$this->defaultLanguage}/general/{$channel}/{$templateFile}";
        }
        
        // Priority 6: Base template in default language (if different)
        if ($language !== $this->defaultLanguage) {
            $paths[] = "{$this->basePath}/{$this->defaultLanguage}/base/{$channel}/{$templateFile}";
        }
        
        return $paths;
    }
    
    /**
     * Substitute variables in template content
     *
     * @param string $content Template content
     * @param array $data Variable data
     * @return string Content with substituted variables
     */
    private function substituteVariables(string $content, array $data): string
    {
        // Handle nested array data
        $flatData = $this->flattenArray($data);
        
        // Replace variables in {{variable}} format
        foreach ($flatData as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }
        
        // Handle conditional blocks {{#if variable}}...{{/if}}
        $content = $this->processConditionals($content, $flatData);
        
        // Handle loops {{#each items}}...{{/each}}
        $content = $this->processLoops($content, $data);
        
        return $content;
    }
    
    /**
     * Apply channel-specific formatting
     *
     * @param string $content Template content
     * @param string $channel Channel type
     * @return string Formatted content
     */
    private function applyChannelFormatting(string $content, string $channel): string
    {
        switch ($channel) {
            case 'email':
                // Convert line breaks to HTML
                $content = nl2br($content);
                break;
                
            case 'sms':
                // Limit length and remove HTML
                $content = strip_tags($content);
                $content = substr($content, 0, 160);
                break;
                
            case 'whatsapp':
                // Keep emojis and formatting
                $content = $this->preserveWhatsAppFormatting($content);
                break;
                
            case 'telegram':
                // Keep HTML formatting
                $content = $this->preserveTelegramFormatting($content);
                break;
                
            case 'push':
                // Limit title and body length
                $content = $this->formatPushNotification($content);
                break;
        }
        
        return $content;
    }
    
    /**
     * Get fallback template when processing fails
     *
     * @param string $template Template name
     * @param string $channel Channel type
     * @param array $data Variable data
     * @return string Fallback template content
     */
    private function getFallbackTemplate(string $template, string $channel, array $data): string
    {
        $fallbacks = [
            'email' => 'Hello {{name|Customer}}, you have a new notification.',
            'sms' => 'Notification: {{message|You have a new update}}',
            'whatsapp' => '📢 {{message|You have a new notification}}',
            'telegram' => '<b>Notification</b>\n{{message|You have a new update}}',
            'push' => '{{title|Notification}}: {{message|You have a new update}}'
        ];
        
        $fallbackContent = $fallbacks[$channel] ?? $fallbacks['email'];
        
        // Simple variable substitution for fallback
        foreach ($data as $key => $value) {
            $fallbackContent = str_replace('{{' . $key . '}}', (string) $value, $fallbackContent);
        }
        
        // Handle default values in format {{variable|default}}
        $fallbackContent = preg_replace_callback('/\{\{([^|]+)\|([^}]+)\}\}/', function ($matches) {
            return $matches[2]; // Return default value
        }, $fallbackContent);
        
        return $fallbackContent;
    }
    
    /**
     * Generate cache key for template
     *
     * @param string $template Template name
     * @param string $channel Channel type
     * @param string $language Language code
     * @param string|null $service Service name
     * @return string Cache key
     */
    private function generateCacheKey(string $template, string $channel, string $language, ?string $service): string
    {
        $parts = ['template', $template, $channel, $language];
        if ($service) {
            $parts[] = $service;
        }
        return implode(':', $parts);
    }
    
    /**
     * Flatten nested array for variable substitution
     *
     * @param array $array Input array
     * @param string $prefix Key prefix
     * @return array Flattened array
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Process conditional blocks in templates
     *
     * @param string $content Template content
     * @param array $data Variable data
     * @return string Processed content
     */
    private function processConditionals(string $content, array $data): string
    {
        // Handle {{#if variable}}...{{/if}} blocks
        $pattern = '/\{\{#if\s+([^}]+)\}\}(.*?)\{\{\/if\}\}/s';
        
        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $variable = trim($matches[1]);
            $block = $matches[2];
            
            // Check if variable exists and is truthy
            if (isset($data[$variable]) && $data[$variable]) {
                return $block;
            }
            
            return '';
        }, $content);
    }
    
    /**
     * Process loop blocks in templates
     *
     * @param string $content Template content
     * @param array $data Variable data
     * @return string Processed content
     */
    private function processLoops(string $content, array $data): string
    {
        // Handle {{#each items}}...{{/each}} blocks
        $pattern = '/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s';
        
        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $variable = trim($matches[1]);
            $block = $matches[2];
            
            if (!isset($data[$variable]) || !is_array($data[$variable])) {
                return '';
            }
            
            $result = '';
            foreach ($data[$variable] as $index => $item) {
                $itemBlock = $block;
                
                // Replace {{this}} with current item
                $itemBlock = str_replace('{{this}}', (string) $item, $itemBlock);
                
                // Replace {{@index}} with current index
                $itemBlock = str_replace('{{@index}}', (string) $index, $itemBlock);
                
                // Replace item properties if item is array
                if (is_array($item)) {
                    foreach ($item as $key => $value) {
                        $itemBlock = str_replace('{{' . $key . '}}', (string) $value, $itemBlock);
                    }
                }
                
                $result .= $itemBlock;
            }
            
            return $result;
        }, $content);
    }
    
    /**
     * Preserve WhatsApp-specific formatting
     *
     * @param string $content Template content
     * @return string Formatted content
     */
    private function preserveWhatsAppFormatting(string $content): string
    {
        // WhatsApp supports emojis and basic formatting
        // Keep line breaks and emojis as-is
        return $content;
    }
    
    /**
     * Preserve Telegram-specific formatting
     *
     * @param string $content Template content
     * @return string Formatted content
     */
    private function preserveTelegramFormatting(string $content): string
    {
        // Telegram supports HTML formatting
        // Ensure proper HTML tags are preserved
        return $content;
    }
    
    /**
     * Format push notification content
     *
     * @param string $content Template content
     * @return string Formatted content
     */
    private function formatPushNotification(string $content): string
    {
        // Push notifications have title and body limits
        // Extract title and body if formatted as "Title: Body"
        if (strpos($content, ':') !== false) {
            [$title, $body] = explode(':', $content, 2);
            $title = trim(substr($title, 0, 50));
            $body = trim(substr($body, 0, 100));
            return $title . ': ' . $body;
        }
        
        return substr($content, 0, 100);
    }
    
    /**
     * Clear template cache
     *
     * @param string|null $template Specific template to clear, or null for all
     * @return bool Success status
     */
    public function clearCache(?string $template = null): bool
    {
        if ($template) {
            // Clear specific template cache
            foreach (self::SUPPORTED_CHANNELS as $channel) {
                foreach (self::SUPPORTED_LANGUAGES as $language) {
                    $cacheKey = $this->generateCacheKey($template, $channel, $language, null);
                    Cache::forget($cacheKey);
                }
            }
        } else {
            // Clear all template cache
            Cache::flush();
        }
        
        return true;
    }
    
    /**
     * Validate template syntax
     *
     * @param string $content Template content
     * @return array Validation result with errors
     */
    public function validateTemplate(string $content): array
    {
        $errors = [];
        
        // Check for unmatched braces
        $openBraces = substr_count($content, '{{');
        $closeBraces = substr_count($content, '}}');
        
        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unmatched template braces';
        }
        
        // Check for unmatched conditional blocks
        $ifCount = preg_match_all('/\{\{#if\s+[^}]+\}\}/', $content);
        $endIfCount = preg_match_all('/\{\{\/if\}\}/', $content);
        
        if ($ifCount !== $endIfCount) {
            $errors[] = 'Unmatched conditional blocks';
        }
        
        // Check for unmatched loop blocks
        $eachCount = preg_match_all('/\{\{#each\s+[^}]+\}\}/', $content);
        $endEachCount = preg_match_all('/\{\{\/each\}\}/', $content);
        
        if ($eachCount !== $endEachCount) {
            $errors[] = 'Unmatched loop blocks';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get available templates for a channel and language
     *
     * @param string $channel Channel type
     * @param string $language Language code
     * @param string|null $service Service name
     * @return array Available template names
     */
    public function getAvailableTemplates(string $channel, string $language = 'en', ?string $service = null): array
    {
        $templates = [];
        $searchPaths = [];
        
        if ($service) {
            $searchPaths[] = "{$this->basePath}/{$language}/{$service}/{$channel}";
        }
        
        $searchPaths[] = "{$this->basePath}/{$language}/general/{$channel}";
        $searchPaths[] = "{$this->basePath}/{$language}/base/{$channel}";
        
        foreach ($searchPaths as $path) {
            if (File::isDirectory($path)) {
                $files = File::allFiles($path);
                foreach ($files as $file) {
                    $templateName = str_replace(['/', '.txt'], ['.', ''], $file->getRelativePathname());
                    $templates[] = $templateName;
                }
            }
        }
        
        return array_unique($templates);
    }
}
