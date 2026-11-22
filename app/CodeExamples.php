<?php

namespace App;

final class CodeExamples
{
    private const TOKENS = [
        'php' => <<<'REGEX'
~(?<comment>//[^\r\n]*|/\*[\s\S]*?\*/)|(?<string>'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")|(?<keyword>\$[a-zA-Z_]\w*|\b(?:protected|public|private|final|class|extends|function|return|if|else|new|use|throw|true|false|null|array|bool|int|string|void|\d+)\b)~
REGEX,
        'json' => <<<'REGEX'
~(?<keyword>"(?:\\.|[^"\\])*"(?=\s*:)|\b(?:true|false|null|\d+(?:\.\d+)?)\b)|(?<string>"(?:\\.|[^"\\])*")~
REGEX,
        'shell' => <<<'REGEX'
~(?<string>'[^']*'|"(?:\\.|[^"\\])*")|(?<keyword>^[a-z][\w-]*|(?<!\S)--?[a-zA-Z][\w-]*)~m
REGEX,
        'html' => <<<'REGEX'
~(?<comment><!--[\s\S]*?-->)|(?<keyword></?!?[a-zA-Z][\w:-]*|\{%|%\}|\{\{|\}\})|(?<string>"[^"\r\n]*"|'[^'\r\n]*')~
REGEX,
        'javascript' => <<<'REGEX'
~(?<comment>//[^\r\n]*|/\*[\s\S]*?\*/)|(?<string>'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`)|(?<keyword>\b(?:async|await|const|let|if|else|new|throw|try|catch|return|function|true|false|null)\b)~
REGEX,
        'sql' => <<<'REGEX'
~(?<comment>--[^\r\n]*|/\*[\s\S]*?\*/)|(?<string>'(?:''|[^'])*')|(?<keyword>\b(?:CREATE|TABLE|PRIMARY|KEY|NOT|NULL|INTEGER|TEXT|INSERT|SELECT|UPDATE|DELETE|FROM|WHERE|VALUES|SET)\b)~i
REGEX,
        'css' => <<<'REGEX'
~(?<comment>/\*[\s\S]*?\*/)|(?<keyword>:[\w-]+(?=[\s\[{])|--[\w-]+(?=\s*:))|(?<string>#[\da-fA-F]{3,8}\b|\b\d+(?:\.\d+)?(?:rem|em|px|%)?)~
REGEX,
    ];

    public static function highlight(string $html): string
    {
        return preg_replace_callback(
            '~(<code data-language="(php|json|shell|html|css|sql|javascript)">)([^<]*)(</code>)~',
            static fn (array $match): string => $match[1]
                . self::highlightText(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $match[2])
                . $match[4],
            $html,
        ) ?? $html;
    }

    private static function highlightText(string $source, string $language): string
    {
        preg_match_all(self::TOKENS[$language], $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $output = '';
        $offset = 0;
        foreach ($matches as $match) {
            [$token, $start] = $match[0];
            $output .= self::escape(substr($source, $offset, $start - $offset));
            foreach (['comment', 'string', 'keyword'] as $type) {
                if (isset($match[$type]) && $match[$type][1] >= 0) {
                    $output .= '<span class="code-' . $type . '">' . self::escape($token) . '</span>';
                    break;
                }
            }
            $offset = $start + strlen($token);
        }
        return $output . self::escape(substr($source, $offset));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
