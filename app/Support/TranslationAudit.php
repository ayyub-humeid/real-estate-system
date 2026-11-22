<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * Finds user-facing text that has no Arabic translation.
 *
 * Two kinds of keys exist in this app:
 *  - group keys such as `crm.errors.unauthorized` (lang/{locale}/crm.php);
 *  - sentence keys such as `Contract number`, translated in lang/ar.json.
 * Only literal keys can be checked; dynamic ones (built from variables) are skipped.
 */
class TranslationAudit
{
    private const SCAN = ['app', 'resources/views'];

    /**
     * Keys that exist in lang/en/*.php but not in lang/ar/*.php, including ones built
     * dynamically in code (e.g. `crm.requirement_types.`.$type) that a literal scan cannot see.
     *
     * @return string[]
     */
    public function arabicGroupGaps(): array
    {
        $gaps = [];

        foreach (File::glob(lang_path('en/*.php')) as $english) {
            $file = basename($english);
            $arabic = lang_path('ar/'.$file);
            $arabicKeys = is_file($arabic) ? $this->flatten(require $arabic) : [];

            foreach (array_keys($this->flatten(require $english)) as $key) {
                if (! array_key_exists($key, $arabicKeys)) {
                    $gaps[] = basename($file, '.php').'.'.$key;
                }
            }
        }

        return $gaps;
    }

    private const UI_METHODS = 'label|title|heading|placeholder|helperText|description|modalHeading|modalDescription|modalSubmitActionLabel|body|emptyStateHeading|emptyStateDescription|tooltip|hint|prefix|suffix|subheading|successNotificationTitle|navigationLabel|badge|noteTitle';

    /**
     * User-facing text passed straight to a Filament method without __(), which can
     * never be translated. Console commands are internal and excluded.
     *
     * @return string[] "file:line text"
     */
    public function hardcodedText(): array
    {
        $found = [];

        foreach (File::allFiles(base_path('app')) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if (! str_ends_with($relative, '.php') || str_contains($relative, '/Console/')) {
                continue;
            }
            $code = file_get_contents($file->getPathname());

            preg_match_all('/->(?:'.self::UI_METHODS.')\(\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1\s*[,)]/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $hit) {
                $text = $hit[2][0];
                if (! preg_match('/[A-Za-z]{3}/', $text) || preg_match('/^(heroicon|primary|danger|success|warning|info|gray|[a-z_.\-:]+$)/', $text)) {
                    continue;
                }
                $found[] = $relative.':'.(substr_count(substr($code, 0, $hit[0][1]), "\n") + 1).'  '.$text;
            }
        }

        return $found;
    }

    /**
     * Error and validation messages thrown as plain English (`->invalid('x', 'Message')`,
     * `ValidationException::withMessages([...])`, `throw new XException('Message')`), which an
     * Arabic user would read untranslated. Short key-style names are translated downstream.
     *
     * @return string[] "file:line text"
     */
    public function rawMessages(): array
    {
        $patterns = [
            '/->(?:invalid|fail|reject|deny)\(\s*[\'"][^\'"]+[\'"]\s*,\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1\s*\)/',
            '/withMessages\(\s*\[[^\]]*?=>\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1/',
            '/throw new \\\\?(?:\w+\\\\)*\w*Exception\(\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1/',
        ];
        $found = [];

        foreach (File::allFiles(base_path('app')) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if (! str_ends_with($relative, '.php') || $relative === 'app/Support/TranslationAudit.php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                foreach ($matches as $hit) {
                    $text = $hit[2][0];
                    if (! preg_match('/[A-Za-z]{3}/', $text) || preg_match('/^[a-z0-9_.]+$/', $text)) {
                        continue;
                    }
                    $found[] = $relative.':'.(substr_count(substr($code, 0, $hit[0][1]), "\n") + 1).'  '.$text;
                }
            }
        }

        return $found;
    }

    /** @return array<string, mixed> */
    private function flatten(array $items, string $prefix = ''): array
    {
        $flat = [];
        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            is_array($value) ? $flat += $this->flatten($value, $path) : $flat[$path] = $value;
        }

        return $flat;
    }

    /** @return array{missing_group: array<string, string[]>, missing_sentence: array<string, string[]>, bad_placeholders: array<string, string>} key => files */
    public function run(): array
    {
        $json = $this->json('ar');
        $missingGroup = $missingSentence = $badPlaceholders = [];

        foreach ($this->arabicGroupGaps() as $key) {
            $missingGroup[$key.' [ar, not in lang/ar]'] = ['lang/ar'];
        }

        foreach ($this->usedKeys() as $key => $files) {
            if ($this->isGroupKey($key)) {
                foreach (['ar', 'en'] as $locale) {
                    if (! Lang::has($key, $locale, false)) {
                        $missingGroup[$key.' ['.$locale.']'] = $files;
                    }
                }
                continue;
            }

            if (! array_key_exists($key, $json)) {
                $missingSentence[$key] = $files;
            } elseif ($this->placeholders($key) !== $this->placeholders((string) $json[$key])) {
                $badPlaceholders[$key] = (string) $json[$key];
            }
        }

        return ['missing_group' => $missingGroup, 'missing_sentence' => $missingSentence, 'bad_placeholders' => $badPlaceholders];
    }

    /** @return array<string, string[]> literal key => files using it */
    public function usedKeys(): array
    {
        $used = [];

        foreach (self::SCAN as $dir) {
            if (! is_dir(base_path($dir))) {
                continue;
            }
            foreach (File::allFiles(base_path($dir)) as $file) {
                if (! preg_match('/\.php$/', $file->getFilename())) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                if (in_array($relative, ['app/Support/TranslationAudit.php', 'app/Console/Commands/FindMissingTranslations.php'], true)) {
                    continue;
                }
                $code = file_get_contents($file->getPathname());

                foreach ($this->literalKeys($code) as $key) {
                    $used[$key][$relative] = $relative;
                }
            }
        }

        return array_map('array_values', $used);
    }

    /** @return string[] */
    private function literalKeys(string $code): array
    {
        $keys = [];
        // __('x'), trans('x'), trans_choice('x'), @lang('x'): single or double quoted literals only.
        preg_match_all('/(?<![\w>:$])(?:__|trans|trans_choice|@lang)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")\s*[,)]/', $code, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1] !== '' ? stripcslashes($match[1]) : stripcslashes($match[2] ?? '');
            // Keys like `filament-shield::x.y` belong to vendor packages that ship their own translations.
            if ($key !== '' && ! str_contains($key, '::') && ! preg_match('/^[\s\p{P}\p{S}\d]*$/u', $key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function isGroupKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*(\.[a-zA-Z0-9_\-]+)+$/', $key) && ! str_contains($key, ' ');
    }

    /** @return string[] */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[A-Za-z_]+/', $text, $found);
        $names = array_unique($found[0]);
        sort($names);

        return $names;
    }

    /** @return array<string, mixed> */
    public function json(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }
}
