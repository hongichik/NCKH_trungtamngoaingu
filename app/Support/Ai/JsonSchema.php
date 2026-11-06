<?php

namespace App\Support\Ai;

/**
 * Tiện ích JSON Schema tối giản:
 *  - strict(): chuyển schema về dạng "strict" mà OpenAI Structured Outputs yêu cầu
 *    (mọi object: additionalProperties=false, mọi thuộc tính đều required).
 *  - validate(): kiểm tra lại dữ liệu AI trả về (type, required, enum, items).
 */
class JsonSchema
{
    public static function strict(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object' || isset($schema['properties'])) {
            $props = $schema['properties'] ?? [];
            foreach ($props as $k => $p) {
                $props[$k] = self::strict($p);
            }
            // Object rỗng phải mã hoá thành {} chứ không phải [].
            $schema['properties'] = $props ?: new \stdClass;
            $schema['required'] = array_keys($props);
            $schema['additionalProperties'] = false;
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::strict($schema['items']);
        }

        return $schema;
    }

    /**
     * @return list<string> danh sách lỗi (rỗng = hợp lệ)
     */
    public static function validate(mixed $data, array $schema, string $path = '$'): array
    {
        $errors = [];
        $type = $schema['type'] ?? null;
        $types = is_array($type) ? $type : ($type ? [$type] : []);

        if ($types && ! self::matchesAny($data, $types)) {
            return ["{$path}: cần kiểu ".implode('|', $types).', nhận '.get_debug_type($data)];
        }

        if (isset($schema['enum']) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = "{$path}: giá trị không nằm trong danh sách cho phép";
        }

        $isObject = is_array($data) && ($data === [] || ! array_is_list($data));
        if ($isObject && (in_array('object', $types, true) || isset($schema['properties']))) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $data)) {
                    $errors[] = "{$path}.{$key}: thiếu";
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $sub) {
                if (array_key_exists($key, $data)) {
                    $errors = array_merge($errors, self::validate($data[$key], $sub, "{$path}.{$key}"));
                }
            }
        }

        if (is_array($data) && in_array('array', $types, true) && isset($schema['items'])) {
            foreach ($data as $i => $item) {
                $errors = array_merge($errors, self::validate($item, $schema['items'], "{$path}[{$i}]"));
            }
        }

        return $errors;
    }

    private static function matchesAny(mixed $v, array $types): bool
    {
        foreach ($types as $t) {
            $ok = match ($t) {
                'object' => is_array($v) && ($v === [] || ! array_is_list($v)),
                'array' => is_array($v) && array_is_list($v),
                'string' => is_string($v),
                'integer' => is_int($v),
                'number' => is_int($v) || is_float($v),
                'boolean' => is_bool($v),
                'null' => $v === null,
                default => true,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
