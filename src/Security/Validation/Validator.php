<?php

declare(strict_types=1);

namespace Rocket\Security\Validation;

/**
 * Class Validator
 *
 * Expressive, zero-dependency input validator.
 */
class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];
    protected array $validated = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): static
    {
        return new static($data, $rules);
    }

    /**
     * Run validation and return validated fields or throw ValidationException.
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->validated;
    }

    /**
     * Check if validation fails.
     */
    public function fails(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $fieldRules) {
            $rulesList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;

            foreach ($rulesList as $rule) {
                $ruleName = $rule;
                $parameters = [];

                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $parameters = explode(',', $paramStr);
                }

                $this->validateRule($field, $value, $ruleName, $parameters);
            }

            if (!isset($this->errors[$field]) && array_key_exists($field, $this->data)) {
                $this->validated[$field] = $value;
            }
        }

        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function validateRule(string $field, mixed $value, string $rule, array $params): void
    {
        $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }

    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    protected function validateRequired(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "The {$field} field is required.");
        }
    }

    protected function validateEmail(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, "The {$field} must be a valid email address.");
        }
    }

    protected function validateNumeric(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, "The {$field} must be a number.");
        }
    }

    protected function validateInteger(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, "The {$field} must be an integer.");
        }
    }

    protected function validateMin(string $field, mixed $value, array $params): void
    {
        $min = (float) ($params[0] ?? 0);

        if (is_numeric($value)) {
            if ((float) $value < $min) {
                $this->addError($field, "The {$field} must be at least {$min}.");
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) < $min) {
                $this->addError($field, "The {$field} must be at least {$min} characters.");
            }
        } elseif (is_array($value)) {
            if (count($value) < $min) {
                $this->addError($field, "The {$field} must have at least {$min} items.");
            }
        }
    }

    protected function validateMax(string $field, mixed $value, array $params): void
    {
        $max = (float) ($params[0] ?? 0);

        if (is_numeric($value)) {
            if ((float) $value > $max) {
                $this->addError($field, "The {$field} may not be greater than {$max}.");
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) > $max) {
                $this->addError($field, "The {$field} may not be greater than {$max} characters.");
            }
        } elseif (is_array($value)) {
            if (count($value) > $max) {
                $this->addError($field, "The {$field} may not have more than {$max} items.");
            }
        }
    }

    protected function validateIn(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !in_array((string) $value, $params, true)) {
            $this->addError($field, "The selected {$field} is invalid.");
        }
    }

    protected function validateSame(string $field, mixed $value, array $params): void
    {
        $otherField = $params[0] ?? '';
        $otherVal = $this->data[$otherField] ?? null;

        if ($value !== $otherVal) {
            $this->addError($field, "The {$field} and {$otherField} must match.");
        }
    }

    protected function validateAlpha(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !preg_match('/^[\pL\pM]+$/u', (string) $value)) {
            $this->addError($field, "The {$field} must only contain letters.");
        }
    }
}
