<?php

namespace Tests\Feature;

use App\Support\TranslationAudit;
use Tests\TestCase;

/**
 * Every user-facing text must exist in Arabic. This fails the build when new code
 * uses a sentence or key that nobody translated, so Arabic users never see English
 * or a raw key like `crm.errors.something`. Run `php artisan lang:find-missing --list`
 * to see exactly what to add.
 */
class TranslationCoverageTest extends TestCase
{
    private function audit(): array
    {
        return app(TranslationAudit::class)->run();
    }

    public function test_every_group_key_used_in_code_exists_in_arabic_and_english(): void
    {
        $this->assertSame([], array_keys($this->audit()['missing_group']), 'Add these keys to lang/en and lang/ar.');
    }

    public function test_every_sentence_used_in_code_is_translated_in_arabic(): void
    {
        $this->assertSame([], array_keys($this->audit()['missing_sentence']), 'Add these sentences to lang/ar.json.');
    }

    public function test_translations_keep_the_same_placeholders_as_their_source(): void
    {
        $this->assertSame([], array_keys($this->audit()['bad_placeholders']), 'A translation lost or renamed a :placeholder.');
    }

    public function test_no_user_facing_text_is_hardcoded_without_a_translation_helper(): void
    {
        $this->assertSame([], app(TranslationAudit::class)->hardcodedText(), 'Wrap these in __() and add the Arabic to lang/ar.json.');
    }

    public function test_the_checker_really_detects_a_missing_translation(): void
    {
        $audit = new class extends TranslationAudit {
            public function usedKeys(): array
            {
                return ['A sentence nobody translated' => ['x.php'], 'crm.unknown.key_for_test' => ['y.php']];
            }
        };

        $result = $audit->run();

        $this->assertArrayHasKey('A sentence nobody translated', $result['missing_sentence']);
        $this->assertArrayHasKey('crm.unknown.key_for_test [ar]', $result['missing_group']);
    }
}
