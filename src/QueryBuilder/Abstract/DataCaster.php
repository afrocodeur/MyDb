<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract;

use DateTime;
use DateTimeInterface;
use Exception;

class DataCaster {

    private static ?DataCaster $instance = null;
    private static array $globalRules = [];

    /**
     * Get the unique caster instance
     */
    private static function getInstance(): DataCaster {
        if (self::$instance === null) {
            self::$instance = new DataCaster();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}/** @var array<string, string|callable> */

    /**
     * Set global default rules applied to all normalizations
     *
     * @param array<string, string|callable> $rules Global rules
     */
    public static function setGlobalRules(array $rules): void {
        self::$globalRules = $rules;
    }

    /**
     * Add a global rule
     *
     * @param string $key Column key
     * @param string|callable $rule Rule to apply
     */
    public static function addGlobalRule(string $key, string|callable $rule): void {
        self::$globalRules[$key] = $rule;
    }

    /**
     * Get current global rules
     *
     * @return array<string, string|callable>
     */
    public static function getGlobalRules(): array {
        return self::$globalRules;
    }

    /**
     * Clear all global rules
     */
    public static function clearGlobalRules(): void {
        self::$globalRules = [];
    }

    /**
     * Cast data after retrieval from database (PUBLIC ENTRY POINT)
     *
     * @param array<string, string|int|float|bool|null> $row Raw data from database
     * @param array<string, string|callable> $rules Cast rules [key => 'type:param1,param2'|callable]
     * @return array<array<string, string|int|float|bool|array<int|string, mixed>|DateTimeInterface|null>>
     */
    public static function cast(array $row, array $rules): array {
        // Merge global rules with local rules (local rules override global)
        $mergedRules = array_merge(self::$globalRules, $rules);

        return self::getInstance()->castData($row, $mergedRules);
    }

    /**
     * Cast data (internal method)
     *
     * @param array<string, string|int|float|bool|null> $row
     * @param array<string, string|callable> $rules
     * @return array<array<string, string|int|float|bool|array<int|string, mixed>|DateTimeInterface|null>>
     */
    private function castData(array $row, array $rules): array {
        if (empty($rules)) {
            return $row;
        }

        $castedRow = $row;

        foreach ($rules as $key => $rule) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            // If callable, execute it
            if (is_callable($rule)) {
                $castedRow[$key] = $rule($value);
                continue;
            }

            // Parse type and parameters
            [$type, $params] = $this->parseRule($rule);

            // Automatic type conversion
            $castedRow[$key] = $this->convertToType($value, $type, $params);
        }

        return $castedRow;
    }

    /**
     * Parse rule string to extract type and parameters
     * Examples: 'date:Y-m-d', 'array:|', 'int'
     *
     * @param string $rule Rule string
     * @return array{0: string, 1: array<int, string>}
     */
    private function parseRule(string $rule): array {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        $parts = explode(':', $rule, 2);
        $type = $parts[0];
        $params = explode(',', $parts[1]);

        return [$type, $params];
    }

    /**
     * Convert value to specified type
     *
     * @param string|int|float|bool|null $value
     * @param string $type
     * @param array<int, string> $params
     * @return string|int|float|bool|array<int|string, mixed>|DateTimeInterface|null
     */
    private function convertToType(string|int|float|bool|null $value, string $type, array $params): string|int|float|bool|array|DateTimeInterface|null {
        return match(strtolower($type)) {
            'string', 'str' => $this->string($value),
            'int', 'integer' => $this->int($value),
            'float', 'double', 'decimal' => $this->float($value, $params),
            'bool', 'boolean' => $this->bool($value),
            'array' => $this->array($value, $params),
            'json' => $this->json($value),
            'date', 'datetime' => $this->date($value, $params[0] ?? null),
            'csv', 'comma' => $this->csv($value, $params[0] ?? ','),
            'pipe' => $this->pipe($value, $params[0] ?? '|'),
            'separator', 'sep' => $this->separator($value, $params[0] ?? ','),
            'null' => null,
            default => $value
        };
    }

    /**
     * Convert to string
     * Accepts: string, int, float, null
     */
    private function string(string|int|float|null $value): string {
        if ($value === null) {
            return '';
        }

        return (string)$value;
    }

    /**
     * Convert to int
     * Accepts: int, string, null
     */
    private function int(int|string|null $value): int {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }

        return 0;
    }

    /**
     * Convert to float/double/decimal
     * Accepts: float, int, string, null
     *
     * @param float|int|string|null $value Value to convert
     * @param array<int, string> $params [decimals] - decimals: number of decimals for rounding
     */
    private function float(float|int|string|null $value, array $params = []): float {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $decimals = isset($params[0]) && is_numeric($params[0]) ? (int)$params[0] : null;

        $result = 0.0;

        if (is_float($value) || is_int($value)) {
            $result = (float)$value;
        } else if (is_string($value) && is_numeric($value)) {
            $result = (float)$value;
        }

        // If decimals specified, round
        if ($decimals !== null) {
            $result = round($result, $decimals);
        }

        return $result;
    }

    /**
     * Convert to bool
     * Accepts: int, string, null
     */
    private function bool(int|string|null $value): bool {
        if ($value === null) {
            return false;
        }

        // Handle integer representations
        if (is_int($value)) {
            return $value !== 0;
        }

        // Handle string representations
        if (is_string($value)) {
            if ($value === '') {
                return false;
            }

            $lower = strtolower(trim($value));

            // True values
            if (in_array($lower, ['1', 'true', 'yes', 'on', 'y'], true)) {
                return true;
            }

            // False values
            if (in_array($lower, ['0', 'false', 'no', 'off', 'n', ''], true)) {
                return false;
            }

            // Numeric strings
            if (is_numeric($value)) {
                return (float)$value !== 0.0;
            }
        }

        return false;
    }

    /**
     * Convert to array
     * Accepts: string, null
     *
     * @param string|null $value Value to convert
     * @param array<int, string> $params [separator] - separator for string splitting
     * @return array<int, string>
     */
    private function array(string|null $value, array $params = []): array {
        // If value is null or empty string, return empty array
        if ($value === null || $value === '') {
            return [];
        }

        $separator = $params[0] ?? ',';
        $result = explode($separator, $value);
        // Trim whitespace from each element and remove empty values
        return array_values(array_filter(array_map('trim', $result), fn($v) => $v !== ''));
    }

    /**
     * Convert JSON string to array
     * Accepts: string, null
     *
     * @return array<string|int, mixed>|null
     */
    private function json(string|null $value): ?array {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Convert to DateTime object
     * Accepts: string, int, null
     *
     * @param string|int|null $value Value to convert
     * @param string|null $format Expected format (for validation)
     */
    private function date(string|int|null $value, ?string $format = null): ?DateTimeInterface {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            try {
                return new DateTime($value);
            } catch (Exception $e) {
                return null;
            }
        }

        if (is_int($value)) {
            try {
                $date = new DateTime();
                $date->setTimestamp($value);
                return $date;
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Convert CSV string to array
     * Accepts: string, null
     *
     * @param string|null $value Value to convert
     * @param string $separator Separator (default: ',')
     * @return array<int, string>
     */
    private function csv(string|null $value, string $separator = ','): array {
        if ($value === null || $value === '') {
            return [];
        }

        $result = explode($separator, $value);
        return array_values(array_filter(array_map('trim', $result), fn($v) => $v !== ''));
    }

    /**
     * Convert pipe-separated string to array
     * Accepts: string, null
     *
     * @param string|null $value Value to convert
     * @param string $separator Separator (default: '|')
     * @return array<int, string>
     */
    private function pipe(string|null $value, string $separator = '|'): array {
        if ($value === null || $value === '') {
            return [];
        }

        $result = explode($separator, $value);
        return array_values(array_filter(array_map('trim', $result), fn($v) => $v !== ''));
    }

    /**
     * Convert string with custom separator to array
     * Accepts: string, null
     *
     * @param string|null $value Value to convert
     * @param string $separator Custom separator
     * @return array<int, string>
     */
    private function separator(string|null $value, string $separator): array {
        if ($value === null || $value === '') {
            return [];
        }

        $result = explode($separator, $value);
        return array_values(array_filter(array_map('trim', $result), fn($v) => $v !== ''));
    }
}