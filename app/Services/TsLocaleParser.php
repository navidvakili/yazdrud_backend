<?php

namespace App\Services;

/**
 * Converts a TypeScript locale module (the public site's src/locales/<code>.ts)
 * into a plain PHP array so the backend can serve it as JSON at runtime.
 *
 * The admin locale editor saves the raw TS file (source of truth), and the
 * public site statically imports these files at build time. To let edits apply
 * on an already-built site, this parser extracts the object literal so the
 * backend can serve the translations as JSON on the fly.
 *
 * Supported subset (matches the generated locale files):
 *   const <name> = { ... };
 *   export default <name>;
 * - quoted string keys, bare identifier keys
 * - string values (single or double quoted, with \n \t \r \uXXXX escapes)
 * - nested objects, arrays, numbers, booleans, null
 * - trailing commas, // line comments and /* block comments *​/
 */
class TsLocaleParser
{
    /**
     * Parse a locale module into a PHP array. Returns null on failure.
     */
    public static function parse(string $source): ?array
    {
        $source = preg_replace('/^\s*const\s+[A-Za-z_$][A-Za-z0-9_$]*\s*=\s*/', '', $source);
        $source = preg_replace('/\s*export\s+default\s+[A-Za-z_$][A-Za-z0-9_$]*\s*;?\s*$/', '', $source);

        if ($source === null || trim($source) === '') {
            return null;
        }

        $parser = new self();
        $offset = 0;
        $value = $parser->parseValue($source, $offset);

        if ($value === null) {
            return null;
        }

        // Allow trailing whitespace and an optional closing semicolon.
        while (isset($source[$offset])) {
            $char = $source[$offset];
            if (ctype_space($char) || $char === ';') {
                $offset++;
                continue;
            }
            break;
        }

        return $offset >= strlen($source) ? $value : null;
    }

    /**
     * @return array|int|float|string|bool|null
     */
    private function parseValue(string $s, int &$i): mixed
    {
        $this->skipWs($s, $i);

        if (!isset($s[$i])) {
            return null;
        }

        $char = $s[$i];

        if ($char === '{') {
            return $this->parseObject($s, $i);
        }

        if ($char === '[') {
            return $this->parseArray($s, $i);
        }

        if ($char === "'" || $char === '"') {
            return $this->parseString($s, $i);
        }

        if ($char === '-' || ctype_digit($char)) {
            return $this->parseNumber($s, $i);
        }

        // Literal keywords: true / false / null
        foreach (['true' => true, 'false' => false, 'null' => null] as $word => $literal) {
            if (substr($s, $i, strlen($word)) === $word) {
                $next = $s[$i + strlen($word)] ?? '';
                if (!isset($next) || preg_match('/[A-Za-z0-9_$]/', $next) !== 1) {
                    $i += strlen($word);

                    return $literal;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseObject(string $s, int &$i): ?array
    {
        $i++; // consume '{'
        $result = [];

        $this->skipWs($s, $i);

        if (($s[$i] ?? '') === '}') {
            $i++;

            return $result;
        }

        while (true) {
            $this->skipWs($s, $i);

            // Key: quoted string or bare identifier.
            $char = $s[$i] ?? null;
            $key = null;

            if ($char === "'" || $char === '"') {
                $key = $this->parseString($s, $i);
            } elseif ($char !== null && preg_match('/[A-Za-z_$]/', $char) === 1) {
                $start = $i;
                while (isset($s[$i]) && preg_match('/[A-Za-z0-9_$]/', $s[$i]) === 1) {
                    $i++;
                }
                $key = substr($s, $start, $i - $start);
            }

            if ($key === null) {
                return null;
            }

            $this->skipWs($s, $i);
            if (($s[$i] ?? '') !== ':') {
                return null;
            }
            $i++; // consume ':'

            $value = $this->parseValue($s, $i);
            if ($value === null) {
                return null;
            }

            $result[$key] = $value;

            $this->skipWs($s, $i);
            $char = $s[$i] ?? null;

            if ($char === ',') {
                $i++;
                continue;
            }
            if ($char === '}') {
                $i++;

                return $result;
            }

            return null;
        }
    }

    /**
     * @return array<int, mixed>|null
     */
    private function parseArray(string $s, int &$i): ?array
    {
        $i++; // consume '['
        $result = [];

        $this->skipWs($s, $i);

        if (($s[$i] ?? '') === ']') {
            $i++;

            return $result;
        }

        while (true) {
            $value = $this->parseValue($s, $i);
            if ($value === null) {
                return null;
            }
            $result[] = $value;

            $this->skipWs($s, $i);
            $char = $s[$i] ?? null;

            if ($char === ',') {
                $i++;
                continue;
            }
            if ($char === ']') {
                $i++;

                return $result;
            }

            return null;
        }
    }

    private function parseString(string $s, int &$i): ?string
    {
        $quote = $s[$i];
        $i++;
        $out = '';
        $len = strlen($s);

        while ($i < $len) {
            $char = $s[$i];

            if ($char === $quote) {
                $i++;

                return $out;
            }

            if ($char === '\\') {
                $i++;
                if ($i >= $len) {
                    return null;
                }
                $esc = $s[$i];
                switch ($esc) {
                    case 'n':
                        $out .= "\n";
                        break;
                    case 't':
                        $out .= "\t";
                        break;
                    case 'r':
                        $out .= "\r";
                        break;
                    case 'b':
                        $out .= "\x08";
                        break;
                    case 'f':
                        $out .= "\x0C";
                        break;
                    case 'u':
                        $hex = substr($s, $i + 1, 4);
                        if (preg_match('/^[0-9a-fA-F]{4}$/', $hex) === 1) {
                            $out .= mb_convert_encoding(pack('H*', $hex), 'UTF-8', 'UTF-16BE');
                            $i += 4;
                        }
                        break;
                    default:
                        $out .= $esc; // \\ \" \' \/ etc.
                }
                $i++;
                continue;
            }

            $out .= $char;
            $i++;
        }

        return null; // unterminated string
    }

    private function parseNumber(string $s, int &$i): int|float|null
    {
        $start = $i;

        if (($s[$i] ?? '') === '-') {
            $i++;
        }
        while (isset($s[$i]) && ctype_digit($s[$i])) {
            $i++;
        }
        if (($s[$i] ?? '') === '.') {
            $i++;
            while (isset($s[$i]) && ctype_digit($s[$i])) {
                $i++;
            }
        }
        if (($s[$i] ?? '') === 'e' || ($s[$i] ?? '') === 'E') {
            $i++;
            if (($s[$i] ?? '') === '+' || ($s[$i] ?? '') === '-') {
                $i++;
            }
            while (isset($s[$i]) && ctype_digit($s[$i])) {
                $i++;
            }
        }

        $token = substr($s, $start, $i - $start);
        if ($token === '' || $token === '-') {
            return null;
        }

        if (str_contains($token, '.') || stripos($token, 'e') !== false) {
            return (float) $token;
        }

        return (int) $token;
    }

    private function skipWs(string $s, int &$i): void
    {
        $len = strlen($s);

        while ($i < $len) {
            $char = $s[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Line comment: // ...
            if ($char === '/' && ($s[$i + 1] ?? '') === '/') {
                $i += 2;
                while ($i < $len && $s[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Block comment: /* ... */
            if ($char === '/' && ($s[$i + 1] ?? '') === '*') {
                $end = strpos($s, '*/', $i + 2);
                if ($end === false) {
                    $i = $len;
                    return;
                }
                $i = $end + 2;
                continue;
            }

            break;
        }
    }
}
