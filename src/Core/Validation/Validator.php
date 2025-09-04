<?php

declare(strict_types=1);

namespace Northvia\Core\Validation;

/**
 * Simple validation class
 */
class Validator
{
    private array $errors = [];

    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules): ValidationResult
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules, $data);
        }

        return new ValidationResult(empty($this->errors), $this->errors);
    }

    /**
     * Validate a single field
     */
    private function validateField(string $field, $value, string $rules, array $allData): void
    {
        $ruleList = explode('|', $rules);

        foreach ($ruleList as $rule) {
            $this->applyRule($field, $value, $rule, $allData);
        }
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, $value, string $rule, array $allData): void
    {
        $parameters = [];
        
        if (strpos($rule, ':') !== false) {
            [$rule, $parameterString] = explode(':', $rule, 2);
            $parameters = explode(',', $parameterString);
        }

        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;

            case 'string':
                if (!is_string($value) && $value !== null) {
                    $this->addError($field, "The {$field} field must be a string.");
                }
                break;

            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} field must be a valid email address.");
                }
                break;

            case 'min':
                $min = (int) $parameters[0];
                if (is_string($value) && strlen($value) < $min) {
                    $this->addError($field, "The {$field} field must be at least {$min} characters.");
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, "The {$field} field must be at least {$min}.");
                }
                break;

            case 'max':
                $max = (int) $parameters[0];
                if (is_string($value) && strlen($value) > $max) {
                    $this->addError($field, "The {$field} field must not exceed {$max} characters.");
                } elseif (is_numeric($value) && $value > $max) {
                    $this->addError($field, "The {$field} field must not exceed {$max}.");
                }
                break;

            case 'in':
                if ($value !== null && !in_array($value, $parameters)) {
                    $this->addError($field, "The selected {$field} is invalid.");
                }
                break;

            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    $this->addError($field, "The {$field} field must be true or false.");
                }
                break;

            case 'date':
                if ($value !== null && !strtotime($value)) {
                    $this->addError($field, "The {$field} field must be a valid date.");
                }
                break;

            case 'same':
                $other = $parameters[0];
                if ($value !== ($allData[$other] ?? null)) {
                    $this->addError($field, "The {$field} field must match {$other}.");
                }
                break;

            case 'accepted':
                if (!in_array($value, [true, 1, '1', 'true', 'yes', 'on'])) {
                    $this->addError($field, "The {$field} field must be accepted.");
                }
                break;

            case 'nullable':
                // This rule allows null values, so we don't need to do anything
                break;

            case 'size':
                $size = (int) $parameters[0];
                if (is_string($value) && strlen($value) !== $size) {
                    $this->addError($field, "The {$field} field must be exactly {$size} characters.");
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->addError($field, "The {$field} field must be numeric.");
                }
                break;

            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The {$field} field must be an integer.");
                }
                break;

            case 'url':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} field must be a valid URL.");
                }
                break;

            case 'regex':
                $pattern = $parameters[0];
                if ($value !== null && !preg_match($pattern, $value)) {
                    $this->addError($field, "The {$field} field format is invalid.");
                }
                break;
        }
    }

    /**
     * Add an error message
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }
}

/**
 * Validation result class
 */
class ValidationResult
{
    private bool $valid;
    private array $errors;

    public function __construct(bool $valid, array $errors)
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }

        return null;
    }
}