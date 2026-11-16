<?php

/** Reads data literals from the two requested tables; never executes dump SQL. */
final class LegacyRenbutSql
{
    public static function read(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read SQL backup.');
        }
        $tables = ['akuns' => [], 'rencana_kebutuhans' => []];
        preg_match_all('/^INSERT INTO `(akuns|rencana_kebutuhans)` \(([^)]+)\) VALUES\s*/m', $sql, $headers, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($headers as $header) {
            $table = $header[1][0];
            preg_match_all('/`([^`]+)`/', $header[2][0], $matches);
            $columns = $matches[1];
            $position = $header[0][1] + strlen($header[0][0]);
            while (true) {
                self::space($sql, $position);
                if (($sql[$position++] ?? '') !== '(') {
                    throw new RuntimeException('Expected row opening in '.$table);
                }
                $values = [];
                foreach ($columns as $index => $column) {
                    self::space($sql, $position);
                    $values[$column] = self::literal($sql, $position);
                    self::space($sql, $position);
                    $expected = $index === count($columns) - 1 ? ')' : ',';
                    if (($sql[$position++] ?? '') !== $expected) {
                        throw new RuntimeException('Invalid row separator in '.$table);
                    }
                }
                $id = (int) ($values['id'] ?? 0);
                if ($id <= 0 || isset($tables[$table][$id])) {
                    throw new RuntimeException('Missing or duplicated source ID in '.$table.': '.$id);
                }
                $tables[$table][$id] = $values;
                self::space($sql, $position);
                $separator = $sql[$position++] ?? '';
                if ($separator === ';') {
                    break;
                }
                if ($separator !== ',') {
                    throw new RuntimeException('Unterminated INSERT for '.$table);
                }
            }
        }
        foreach ($tables as $name => $rows) {
            if ($rows === []) {
                throw new RuntimeException('No source rows found for '.$name);
            }
        }
        return $tables;
    }

    private static function space(string $sql, int &$position): void
    {
        $position += strspn($sql, " \t\r\n", $position);
    }

    private static function literal(string $sql, int &$position): ?string
    {
        if (($sql[$position] ?? '') !== "'") {
            $start = $position;
            while (isset($sql[$position]) && $sql[$position] !== ',' && $sql[$position] !== ')') {
                $position++;
            }
            $value = trim(substr($sql, $start, $position - $start));
            if (strtoupper($value) === 'NULL') {
                return null;
            }
            if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
                throw new RuntimeException('Only numeric, string and NULL literals are accepted.');
            }
            return $value;
        }
        $position++;
        $value = '';
        $escapes = ['0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a"];
        while (isset($sql[$position])) {
            $char = $sql[$position++];
            if ($char === '\\') {
                if (!isset($sql[$position])) {
                    break;
                }
                $escaped = $sql[$position++];
                $value .= $escapes[$escaped] ?? $escaped;
            } elseif ($char === "'") {
                if (($sql[$position] ?? '') === "'") {
                    $value .= "'";
                    $position++;
                } else {
                    return $value;
                }
            } else {
                $value .= $char;
            }
        }
        throw new RuntimeException('Unterminated string in SQL backup.');
    }
}
