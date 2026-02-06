<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Exception;

/**
 * Validation Micro Procedure
 * 
 * Provides centralized validation rules, cross-field validation,
 * and standardized error responses for all cross-service operations.
 */
trait ValidationProcedure
{
    /**
     * Validate data against comprehensive rules
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function validateData(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'data' => ['required' => true, 'type' => 'array'],
                'rules' => ['required' => true, 'type' => 'array'],
                'custom_messages' => ['type' => 'array'],
                'stop_on_first_failure' => ['type' => 'bool']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $data = $params['data'];
            $rules = $params['rules'];
            $customMessages = $params['custom_messages'] ?? [];
            $stopOnFirstFailure = $params['stop_on_first_failure'] ?? false;

            // Perform comprehensive validation
            $validationResult = $this->performValidation($data, $rules, $customMessages, $stopOnFirstFailure);

            // Record metrics
            $this->recordMetric('validation_performed', 1, [
                'success' => $validationResult['success'],
                'field_count' => count($data),
                'rule_count' => count($rules),
                'error_count' => count($validationResult['errors'])
            ]);

            $this->log('debug', 'Data validation completed', [
                'success' => $validationResult['success'],
                'field_count' => count($data),
                'error_count' => count($validationResult['errors'])
            ]);

            return $this->successResponse($validationResult, 
                $validationResult['success'] ? 'Validation passed' : 'Validation failed');

        } catch (Exception $e) {
            $this->log('error', 'Validation procedure failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Validation procedure failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate API request format
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function validateApiRequest(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'request_data' => ['required' => true, 'type' => 'array'],
                'endpoint' => ['required' => true, 'type' => 'string'],
                'method' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $requestData = $params['request_data'];
            $endpoint = $params['endpoint'];
            $method = strtoupper($params['method']);

            // Get validation rules for the specific endpoint
            $rules = $this->getEndpointValidationRules($endpoint, $method);
            
            if (empty($rules)) {
                return $this->successResponse([
                    'success' => true,
                    'message' => 'No validation rules defined for this endpoint'
                ], 'API request validation skipped');
            }

            // Validate request data
            $validationResult = $this->performValidation($requestData, $rules);

            // Add API-specific validation
            $apiValidation = $this->validateApiSpecificRules($requestData, $endpoint, $method);
            if (!$apiValidation['success']) {
                $validationResult['success'] = false;
                $validationResult['errors'] = array_merge(
                    $validationResult['errors'], 
                    $apiValidation['errors']
                );
            }

            $this->recordMetric('api_validation_performed', 1, [
                'endpoint' => $endpoint,
                'method' => $method,
                'success' => $validationResult['success']
            ]);

            return $this->successResponse($validationResult, 
                $validationResult['success'] ? 'API request validation passed' : 'API request validation failed');

        } catch (Exception $e) {
            $this->log('error', 'API request validation failed', [
                'error' => $e->getMessage(),
                'endpoint' => $params['endpoint'] ?? null
            ]);

            return $this->errorResponse('API request validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate cross-field dependencies
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function validateCrossFields(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'data' => ['required' => true, 'type' => 'array'],
                'cross_field_rules' => ['required' => true, 'type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $data = $params['data'];
            $crossFieldRules = $params['cross_field_rules'];
            $errors = [];

            // Validate cross-field dependencies
            foreach ($crossFieldRules as $ruleName => $rule) {
                $ruleResult = $this->validateCrossFieldRule($data, $rule);
                if (!$ruleResult['success']) {
                    $errors[$ruleName] = $ruleResult['errors'];
                }
            }

            $success = empty($errors);

            $this->recordMetric('cross_field_validation', 1, [
                'success' => $success,
                'rule_count' => count($crossFieldRules),
                'error_count' => count($errors)
            ]);

            return $this->successResponse([
                'success' => $success,
                'errors' => $errors,
                'validated_rules' => array_keys($crossFieldRules)
            ], $success ? 'Cross-field validation passed' : 'Cross-field validation failed');

        } catch (Exception $e) {
            $this->log('error', 'Cross-field validation failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Cross-field validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize input data
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sanitizeData(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'data' => ['required' => true, 'type' => 'array'],
                'sanitization_rules' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $data = $params['data'];
            $sanitizationRules = $params['sanitization_rules'] ?? [];

            $sanitizedData = $this->performSanitization($data, $sanitizationRules);

            $this->recordMetric('data_sanitization', 1, [
                'field_count' => count($data),
                'rule_count' => count($sanitizationRules)
            ]);

            $this->log('debug', 'Data sanitization completed', [
                'field_count' => count($data),
                'sanitized_field_count' => count($sanitizedData)
            ]);

            return $this->successResponse([
                'sanitized_data' => $sanitizedData,
                'original_field_count' => count($data),
                'sanitized_field_count' => count($sanitizedData)
            ], 'Data sanitization completed');

        } catch (Exception $e) {
            $this->log('error', 'Data sanitization failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Data sanitization failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform comprehensive validation
     *
     * @param array $data
     * @param array $rules
     * @param array $customMessages
     * @param bool $stopOnFirstFailure
     * @return array
     */
    private function performValidation(array $data, array $rules, array $customMessages = [], bool $stopOnFirstFailure = false): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldErrors = [];

            // Required validation
            if (isset($fieldRules['required']) && $fieldRules['required'] && $this->isEmpty($value)) {
                $fieldErrors[] = $customMessages[$field]['required'] ?? "Field '{$field}' is required";
                if ($stopOnFirstFailure) {
                    return ['success' => false, 'errors' => [$field => $fieldErrors]];
                }
                continue;
            }

            // Skip other validations if field is empty and not required
            if ($this->isEmpty($value) && (!isset($fieldRules['required']) || !$fieldRules['required'])) {
                continue;
            }

            // Type validation
            if (isset($fieldRules['type'])) {
                if (!$this->validateFieldType($value, $fieldRules['type'])) {
                    $fieldErrors[] = $customMessages[$field]['type'] ?? "Field '{$field}' must be of type {$fieldRules['type']}";
                    if ($stopOnFirstFailure) {
                        return ['success' => false, 'errors' => [$field => $fieldErrors]];
                    }
                }
            }

            // Length validations
            if (isset($fieldRules['min_length'])) {
                if (strlen($value) < $fieldRules['min_length']) {
                    $fieldErrors[] = $customMessages[$field]['min_length'] ?? "Field '{$field}' must be at least {$fieldRules['min_length']} characters";
                }
            }

            if (isset($fieldRules['max_length'])) {
                if (strlen($value) > $fieldRules['max_length']) {
                    $fieldErrors[] = $customMessages[$field]['max_length'] ?? "Field '{$field}' must not exceed {$fieldRules['max_length']} characters";
                }
            }

            // Numeric validations
            if (isset($fieldRules['min']) && is_numeric($value)) {
                if ($value < $fieldRules['min']) {
                    $fieldErrors[] = $customMessages[$field]['min'] ?? "Field '{$field}' must be at least {$fieldRules['min']}";
                }
            }

            if (isset($fieldRules['max']) && is_numeric($value)) {
                if ($value > $fieldRules['max']) {
                    $fieldErrors[] = $customMessages[$field]['max'] ?? "Field '{$field}' must not exceed {$fieldRules['max']}";
                }
            }

            // Pattern validation
            if (isset($fieldRules['pattern'])) {
                if (!preg_match($fieldRules['pattern'], $value)) {
                    $fieldErrors[] = $customMessages[$field]['pattern'] ?? "Field '{$field}' format is invalid";
                }
            }

            // In array validation
            if (isset($fieldRules['in'])) {
                if (!in_array($value, $fieldRules['in'])) {
                    $fieldErrors[] = $customMessages[$field]['in'] ?? "Field '{$field}' must be one of: " . implode(', ', $fieldRules['in']);
                }
            }

            // Custom validation
            if (isset($fieldRules['custom']) && is_callable($fieldRules['custom'])) {
                $customResult = $fieldRules['custom']($value, $data);
                if ($customResult !== true) {
                    $fieldErrors[] = is_string($customResult) ? $customResult : "Field '{$field}' failed custom validation";
                }
            }

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
                if ($stopOnFirstFailure) {
                    return ['success' => false, 'errors' => $errors];
                }
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors,
            'validated_fields' => array_keys($rules)
        ];
    }

    /**
     * Get validation rules for specific endpoint
     *
     * @param string $endpoint
     * @param string $method
     * @return array
     */
    private function getEndpointValidationRules(string $endpoint, string $method): array
    {
        // This would typically load from configuration or database
        $endpointRules = [
            'POST:/api/events/publish' => [
                'event_type' => ['required' => true, 'type' => 'string', 'min_length' => 3],
                'event_data' => ['required' => true, 'type' => 'array'],
                'source_service' => ['required' => true, 'type' => 'string'],
                'target_services' => ['type' => 'array']
            ],
            'POST:/api/cache/set' => [
                'key' => ['required' => true, 'type' => 'string', 'min_length' => 1],
                'value' => ['required' => true],
                'ttl' => ['type' => 'int', 'min' => 1, 'max' => 86400]
            ],
            'POST:/api/services/register' => [
                'service_name' => ['required' => true, 'type' => 'string', 'pattern' => '/^[a-zA-Z0-9_-]+$/'],
                'host' => ['required' => true, 'type' => 'string'],
                'port' => ['required' => true, 'type' => 'int', 'min' => 1, 'max' => 65535]
            ]
        ];

        $key = $method . ':' . $endpoint;
        return $endpointRules[$key] ?? [];
    }

    /**
     * Validate API-specific rules
     *
     * @param array $data
     * @param string $endpoint
     * @param string $method
     * @return array
     */
    private function validateApiSpecificRules(array $data, string $endpoint, string $method): array
    {
        $errors = [];

        // API rate limiting validation
        if ($this->isRateLimited($endpoint, $method)) {
            $errors['rate_limit'] = ['API rate limit exceeded for this endpoint'];
        }

        // Content type validation
        if ($method === 'POST' || $method === 'PUT') {
            if (empty($data)) {
                $errors['content'] = ['Request body cannot be empty for ' . $method . ' requests'];
            }
        }

        // Service-specific validations
        if (strpos($endpoint, '/events/') !== false) {
            if (isset($data['event_type']) && !$this->isValidEventType($data['event_type'])) {
                $errors['event_type'] = ['Invalid event type format'];
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate cross-field rule
     *
     * @param array $data
     * @param array $rule
     * @return array
     */
    private function validateCrossFieldRule(array $data, array $rule): array
    {
        $errors = [];

        switch ($rule['type']) {
            case 'conditional_required':
                if (isset($data[$rule['condition_field']]) && $data[$rule['condition_field']] === $rule['condition_value']) {
                    if (empty($data[$rule['required_field']])) {
                        $errors[] = "Field '{$rule['required_field']}' is required when '{$rule['condition_field']}' is '{$rule['condition_value']}'";
                    }
                }
                break;

            case 'mutually_exclusive':
                $presentFields = array_filter($rule['fields'], function($field) use ($data) {
                    return !empty($data[$field]);
                });
                if (count($presentFields) > 1) {
                    $errors[] = "Fields " . implode(', ', $rule['fields']) . " are mutually exclusive";
                }
                break;

            case 'at_least_one':
                $presentFields = array_filter($rule['fields'], function($field) use ($data) {
                    return !empty($data[$field]);
                });
                if (empty($presentFields)) {
                    $errors[] = "At least one of these fields is required: " . implode(', ', $rule['fields']);
                }
                break;

            case 'date_range':
                if (isset($data[$rule['start_field']]) && isset($data[$rule['end_field']])) {
                    $startDate = strtotime($data[$rule['start_field']]);
                    $endDate = strtotime($data[$rule['end_field']]);
                    if ($startDate >= $endDate) {
                        $errors[] = "'{$rule['start_field']}' must be before '{$rule['end_field']}'";
                    }
                }
                break;
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Perform data sanitization
     *
     * @param array $data
     * @param array $rules
     * @return array
     */
    private function performSanitization(array $data, array $rules): array
    {
        $sanitized = [];

        foreach ($data as $field => $value) {
            $fieldRules = $rules[$field] ?? ['trim', 'strip_tags'];
            $sanitizedValue = $value;

            foreach ($fieldRules as $rule) {
                switch ($rule) {
                    case 'trim':
                        if (is_string($sanitizedValue)) {
                            $sanitizedValue = trim($sanitizedValue);
                        }
                        break;
                    case 'strip_tags':
                        if (is_string($sanitizedValue)) {
                            $sanitizedValue = strip_tags($sanitizedValue);
                        }
                        break;
                    case 'escape_html':
                        if (is_string($sanitizedValue)) {
                            $sanitizedValue = htmlspecialchars($sanitizedValue, ENT_QUOTES, 'UTF-8');
                        }
                        break;
                    case 'lowercase':
                        if (is_string($sanitizedValue)) {
                            $sanitizedValue = strtolower($sanitizedValue);
                        }
                        break;
                    case 'uppercase':
                        if (is_string($sanitizedValue)) {
                            $sanitizedValue = strtoupper($sanitizedValue);
                        }
                        break;
                    case 'remove_whitespace':
                        if (is_string($sanitizedValue)) {
                            $sanitizedValue = preg_replace('/\s+/', '', $sanitizedValue);
                        }
                        break;
                }
            }

            $sanitized[$field] = $sanitizedValue;
        }

        return $sanitized;
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value
     * @return bool
     */
    private function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Validate field type
     *
     * @param mixed $value
     * @param string $type
     * @return bool
     */
    private function validateFieldType($value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'integer':
            case 'int':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'float':
            case 'double':
                return is_float($value) || is_numeric($value);
            case 'boolean':
            case 'bool':
                return is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0]);
            case 'array':
                return is_array($value);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'uuid':
                return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
            case 'json':
                json_decode($value);
                return json_last_error() === JSON_ERROR_NONE;
            case 'date':
                return strtotime($value) !== false;
            default:
                return true;
        }
    }

    /**
     * Check if endpoint is rate limited
     *
     * @param string $endpoint
     * @param string $method
     * @return bool
     */
    private function isRateLimited(string $endpoint, string $method): bool
    {
        // This would integrate with your rate limiting system
        // For now, return false (no rate limiting)
        return false;
    }

    /**
     * Check if event type is valid
     *
     * @param string $eventType
     * @return bool
     */
    private function isValidEventType(string $eventType): bool
    {
        // Event type should follow pattern: service.action (e.g., user.created, order.completed)
        return preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $eventType);
    }
}
