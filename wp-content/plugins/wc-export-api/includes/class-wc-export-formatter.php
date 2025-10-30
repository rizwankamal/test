<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Export_Formatter
{
    public static function to_csv(array $rows, array $headers = null, string $delimiter = ","): string
    {
        if (empty($rows)) {
            $headers = $headers ?: [];
        } else {
            if ($headers === null) {
                // Use union of keys across all rows to avoid missing columns
                $headerSet = [];
                foreach ($rows as $row) {
                    foreach (array_keys($row) as $key) {
                        $headerSet[$key] = true;
                    }
                }
                $headers = array_keys($headerSet);
            }
        }

        $fh = fopen('php://temp', 'r+');
        // Write header row
        fputcsv($fh, $headers, $delimiter);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $key) {
                $value = array_key_exists($key, $row) ? $row[$key] : '';
                $line[] = self::normalize_value($value);
            }
            fputcsv($fh, $line, $delimiter);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return (string) $csv;
    }

    public static function filter_fields(array $rows, array $fields): array
    {
        if (empty($fields)) {
            return $rows;
        }
        $filtered = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($fields as $f) {
                $out[$f] = array_key_exists($f, $row) ? $row[$f] : '';
            }
            $filtered[] = $out;
        }
        return $filtered;
    }

    public static function normalize_value($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }

    public static function safe_filename(string $type, string $ext = 'csv'): string
    {
        $datetime = gmdate('Ymd-His');
        return sprintf('wc-%s-export-%s.%s', $type, $datetime, $ext);
    }
}
