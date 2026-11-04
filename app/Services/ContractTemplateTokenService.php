<?php

namespace App\Services;

/** Keeps legal template placeholders stable without exposing implementation syntax to employees. */
final class ContractTemplateTokenService
{
    public static function normalise(?string $body): string
    {
        return preg_replace_callback(
            // Rich text editors HTML-escape the broken expression, while
            // older Blade-built content may contain it literally. Repair both
            // representations before the employee sees or prints the template.
            '/(?:<|&lt;)\?php\s+echo\s+e\(\s*([a-z_]+)\s*\);\s*\?(?:>|&gt;)/i',
            fn (array $matches): string => '{{'.$matches[1].'}}',
            $body ?? '',
        ) ?? '';
    }
}
