<?php

namespace App\Support;

/**
 * Conservative content limits keep rich legal text safely below mPDF's PCRE
 * parsing limits. They are enforced in the form, domain services, and again
 * immediately before PDF rendering for older imported records.
 */
final class ContractPdfContentLimits
{
    public const TEMPLATE_BODY_MAX_CHARACTERS = 120_000;

    public const TERM_TEXT_MAX_CHARACTERS = 15_000;

    public const TERMS_MAX_COUNT = 20;

    public const TERMS_TOTAL_MAX_CHARACTERS = 120_000;

    public const RENDERED_CONTENT_MAX_CHARACTERS = 240_000;
}
