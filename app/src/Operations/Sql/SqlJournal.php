<?php

declare(strict_types=1);

namespace App\Operations\Sql;

final class SqlJournal
{
    public const string PREFIX = '[sql] ';

    /**
     * @template T
     *
     * @param \Closure(): T $execute
     *
     * @return T
     */
    public function measure(string $sql, \Closure $execute): mixed
    {
        $start = hrtime(true);
        $ok = false;
        try {
            $result = $execute();
            $ok = true;

            return $result;
        } finally {
            $this->record($sql, $start, $ok);
        }
    }

    public function record(string $sql, int $start, bool $ok): void
    {
        $duration = round((hrtime(true) - $start) / 1_000_000, 2);
        $entry = json_encode([
            'query' => self::template($sql),
            'durationMs' => $duration,
            'ok' => $ok,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        if (false !== $entry) {
            @error_log(self::PREFIX.$entry, 4);
        }
    }

    public static function template(string $sql): string
    {
        if (strlen($sql) > 32 * 1024) {
            return 'Запрос превышает лимит отображения';
        }

        $pattern = <<<'REGEX'
            ~
              "(?:[^"]|"")*"                          (*SKIP)(*F)
            | --[^\r\n]*
            | (?<comment> /\* (?: (?&comment) | (?![/][*]|[*][/]). )* \*/ )
            | (?<![\w$]) (?<tag>\$(?:[a-zA-Z_][a-zA-Z_0-9]*)?\$) .*? \k<tag>
            | (?<!\w) [eE]' (?: [^'\\]|\\.|'' )* '
            | ' (?: [^']|'' )* '
            | (?<![\w$]) (?:0[xX][0-9a-fA-F_]+|0[bB][01_]+|0[oO][0-7_]+|[0-9][0-9_]*(?:\.[0-9_]*)?(?:[eE][+-]?[0-9_]+)?|\.[0-9][0-9_]*(?:[eE][+-]?[0-9_]+)?) (?![\w$])
            | ['"] | /\* | \$(?:[a-zA-Z_][a-zA-Z_0-9]*)?\$
            ~sx
            REGEX;

        $incomplete = false;
        $template = preg_replace_callback($pattern, static function (array $match) use (&$incomplete): string {
            if (in_array($match[0], ["'", '"', '/*'], true) || preg_match('/^\$[\w]*\$$/D', $match[0])) {
                $incomplete = true;
            }

            return str_starts_with($match[0], '--') || str_starts_with($match[0], '/*') ? ' ' : '?';
        }, $sql);

        if ($incomplete || null === $template) {
            return 'Шаблон запроса недоступен';
        }

        return strlen($template) > 2048 ? mb_strcut(trim($template), 0, 2048, 'UTF-8').'…' : trim($template);
    }
}
