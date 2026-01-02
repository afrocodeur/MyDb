<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract;


use DateTime;
use DateTimeInterface;

class DataNormalizer {

    private static ?DataNormalizer $instance = null;

    /** @var array<string, string|callable> */
    private static array $globalRules = [];

    /**
     * Get the unique normalizer instance
     */
    private static function getInstance(): DataNormalizer {
        if (self::$instance === null) {
            self::$instance = new DataNormalizer();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}

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
     * Normalize data before insert/update in database (PUBLIC ENTRY POINT)
     *
     * @param array $data Data to normalize
     * @param array $rules Normalization rules [key => 'type:param1,param2'|callable]
     * @return array Normalized data
     */
    public static function normalize(array $data, array $rules): array {
        // Merge global rules with local rules (local rules override global)
        $mergedRules = array_merge(self::$globalRules, $rules);

        return self::getInstance()->normalizeData($data, $mergedRules);
    }

    /**
     * Normalize data (internal method)
     */
    private function normalizeData(array $data, array $rules): array {
        if (empty($rules)) {
            return $data;
        }

        $normalized = $data;

        foreach ($rules as $key => $rule) {
            if (!isset($data[$key])) {
                continue;
            }

            $value = $data[$key];

            // Check if column ends with _id/_by/_uuid and value is an object
            if ($this->shouldAutoExtract($key, $value)) {
                $normalized[$key] = $this->handleObject($value, $key);
                continue;
            }

            // If callable, execute it
            if (is_callable($rule)) {
                $normalized[$key] = $rule($value);
                continue;
            }

            // Parse type and parameters
            [$type, $params] = $this->parseRule($rule);

            // Automatic type conversion
            $normalized[$key] = $this->convertToType($value, $type, $params);
        }

        return $normalized;
    }

    /**
     * Check if column should auto-extract id() or uuid() from object
     *
     * @param string $columnName Column name
     * @param mixed $value Value to check
     * @return bool True if should extract
     */
    private function shouldAutoExtract(string $columnName, mixed $value): bool {
        if (!is_object($value)) {
            return false;
        }

        if ($value instanceof DateTimeInterface) {
            return false;
        }

        // Check if column ends with _id, _by or _uuid
        return str_ends_with($columnName, '_id')
            || str_ends_with($columnName, '_by')
            || str_ends_with($columnName, '_uuid');
    }

    /**
     * Parse rule string to extract type and parameters
     * Examples: 'date:Y-m-d', 'csv:|', 'float:2'
     *
     * @param string $rule Rule string
     * @return array [type, parameters]
     */
    private function parseRule(string $rule): array {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$type, $paramsString] = explode(':', $rule, 2);
        $params = explode(',', $paramsString);

        return [$type, $params];
    }

    /**
     * Convert value to specified type
     */
    private function convertToType(mixed $value, string $type, array $params): mixed {
        return match(strtolower($type)) {
            'string', 'str' => $this->string($value),
            'int', 'integer' => $this->int($value),
            'float', 'double', 'decimal' => $this->float($value, $params),
            'bool', 'boolean' => $this->bool($value),
            'array' => $this->array($value),
            'json' => $this->json($value),
            'date', 'datetime' => $this->date($value, $params[0] ?? 'Y-m-d H:i:s'),
            'csv', 'comma' => $this->csv($value, $params[0] ?? ','),
            'pipe' => $this->pipe($value, $params[0] ?? '|'),
            'separator', 'sep' => $this->separator($value, $params[0] ?? ','),
            'null' => null,
            default => $value
        };
    }

    /**
     * Handle object conversion
     * Try to call uuid() if column ends with _uuid, otherwise id(), or return the object
     *
     * @param object $value Object to extract from
     * @param string $columnName Column name to determine which method to call
     */
    private function handleObject(object $value, string $columnName): string|int|null {
        // Try to call id() method
        if (method_exists($value, 'id')) {
            return $value->id();
        }

        // If column ends with _uuid, try uuid() first
        if (str_ends_with($columnName, '_uuid')) {
            if (method_exists($value, 'uuid')) {
                return $value->uuid();
            }
        }

        return null;
    }

    /**
     * Convert to string
     */
    private function string(mixed $value): string {
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value) || is_bool($value)) {
            return (string)$value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }
        return '';
    }

    /**
     * Convert to int
     */
    private function int(mixed $value): int {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value) || is_bool($value) || is_string($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * Convert to float/double/decimal
     *
     * @param mixed $value Value to convert
     * @param array $params [decimals, format] - decimals: number of decimals, format: 'string' for decimal string
     */
    private function float(mixed $value, array $params = []): float|string {
        $decimals = isset($params[0]) ? (int)$params[0] : null;
        $format = $params[1] ?? null;

        $result = 0.0;

        if (is_float($value)) {
            $result = $value;
        } else if (is_numeric($value) || is_bool($value) || is_string($value)) {
            $result = (float) $value;
        }

        // If decimals specified
        if ($decimals !== null) {
            $result = round($result, $decimals);

            // If format is 'string', return formatted decimal string
            if ($format === 'string') {
                return number_format($result, $decimals, '.', '');
            }
        }

        return $result;
    }

    /**
     * Convert to bool
     */
    private function bool(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value) || is_string($value)) {
            return (bool)$value;
        }
        return false;
    }

    /**
     * Convert to array
     */
    private function array(mixed $value): array {
        if (is_array($value)) {
            return $value;
        }
        return [$value];
    }

    /**
     * Convert to JSON string
     */
    private function json(string|array|object $value): string {
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value) ?: '{}';
    }

    /**
     * Convert to date string format
     *
     * @param DateTimeInterface|string|int $value Value to convert
     * @param string $format Date format (default: 'Y-m-d H:i:s')
     */
    private function date(DateTimeInterface|string|int $value, string $format = 'Y-m-d H:i:s'): string {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value)) {
            try {
                return (new DateTime($value))->format($format);
            } catch (\Exception $e) {
                return $value;
            }
        }

        if (is_int($value)) {
            return (new DateTime())->setTimestamp($value)->format($format);
        }

        return '';
    }

    /**
     * Convert array to CSV (comma-separated)
     *
     * @param array|string $value Value to convert
     * @param string $separator Separator (default: ',')
     */
    private function csv(array|string $value, string $separator = ','): string {
        if (is_string($value)) {
            return $value;
        }
        return implode($separator, $value);
    }

    /**
     * Convert array to pipe-separated
     *
     * @param array|string $value Value to convert
     * @param string $separator Separator (default: '|')
     */
    private function pipe(array|string $value, string $separator = '|'): string {
        if (is_string($value)) {
            return $value;
        }
        return implode($separator, $value);
    }

    /**
     * Convert array with custom separator
     *
     * @param array|string $value Value to convert
     * @param string $separator Custom separator
     */
    private function separator(array|string $value, string $separator): string {
        if (is_string($value)) {
            return $value;
        }
        return implode($separator, $value);
    }
}