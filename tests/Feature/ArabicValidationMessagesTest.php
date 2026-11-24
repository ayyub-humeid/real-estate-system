<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Arabic users must never see an English validation message, dates included. */
class ArabicValidationMessagesTest extends TestCase
{
    public function test_date_rules_speak_arabic_and_use_the_arabic_field_name(): void
    {
        app()->setLocale('ar');

        $errors = Validator::make(
            ['start_date' => '2026-05-10', 'end_date' => '2026-05-01', 'due_date' => 'not a date'],
            ['end_date' => 'after:start_date', 'due_date' => 'date', 'start_date' => 'before_or_equal:2026-01-01', 'valid_until' => 'required|date_format:Y-m-d'],
        )->errors();

        foreach (['end_date', 'due_date', 'start_date', 'valid_until'] as $field) {
            $message = $errors->first($field);
            $this->assertNotEmpty($message, "No error for [$field].");
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{4,}/', preg_replace('/\d{4}-\d{2}-\d{2}|Y-m-d/', '', $message), "[$field] is not fully Arabic: $message");
        }
        $this->assertStringContainsString('تاريخ النهاية', $errors->first('end_date'));
    }
}
