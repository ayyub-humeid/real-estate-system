<?php

namespace App\Console\Commands;

use App\Support\TranslationAudit;
use Illuminate\Console\Command;

class FindMissingTranslations extends Command
{
    protected $signature = 'lang:find-missing {--list : Print every missing key} {--json= : Write missing sentence keys to this JSON file}';

    protected $description = 'List user-facing text used in code that has no Arabic (or English) translation.';

    public function handle(TranslationAudit $audit): int
    {
        $result = $audit->run();

        $this->info('Missing group keys:      '.count($result['missing_group']));
        $this->info('Missing sentence keys:   '.count($result['missing_sentence']));
        $this->info('Placeholder mismatches: '.count($result['bad_placeholders']));

        if ($this->option('list')) {
            foreach ($result['missing_group'] as $key => $files) {
                $this->line("group    {$key}  ({$files[0]})");
            }
            foreach ($result['missing_sentence'] as $key => $files) {
                $this->line("sentence {$key}  ({$files[0]})");
            }
            foreach ($result['bad_placeholders'] as $key => $translation) {
                $this->line("placeholders {$key} => {$translation}");
            }
        }

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode(array_keys($result['missing_sentence']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        return count($result['missing_group']) + count($result['missing_sentence']) + count($result['bad_placeholders']) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
