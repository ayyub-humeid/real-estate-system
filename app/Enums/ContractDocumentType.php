<?php

namespace App\Enums;

enum ContractDocumentType: string
{
    case Supporting = 'supporting';
    case OfficialApprovedPdf = 'official_approved_pdf';
    case SignedFinalPdf = 'signed_final_pdf';

    public function getLabel(): string
    {
        return match ($this) {
            self::Supporting => __('contract_domain.pdf.supporting'),
            self::OfficialApprovedPdf => __('contract_domain.pdf.official'),
            self::SignedFinalPdf => __('contract_domain.pdf.signed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Supporting => 'gray',
            self::OfficialApprovedPdf => 'primary',
            self::SignedFinalPdf => 'success',
        };
    }
}
