<?php

return [
    'statuses' => [
        'draft' => 'Draft',
        'under_review' => 'Under review',
        'revision_required' => 'Revision required',
        'approved' => 'Approved',
        'active' => 'Active',
    ],
    'version_statuses' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted for review',
        'revision_required' => 'Revision required',
        'approved' => 'Approved',
        'superseded' => 'Superseded',
        'signed' => 'Signed',
    ],
    'approval_statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'revision_required' => 'Revision required',
    ],
    'amendment_statuses' => [
        'draft' => 'Draft',
        'effective' => 'Effective',
    ],
    'documents' => [
        'no_version' => 'No contract version is available to receive a document.',
        'submit_then_approve' => 'Submit the contract version for review, then approve it before uploading the legal document.',
        'manage_draft' => 'Use the “Upload contract document” button above this tab. You can also replace or delete draft documents.',
        'wait_for_approval' => 'This contract version is under review. Wait for approval before uploading the legal document.',
        'upload_from_button' => 'Use the “Upload approved legal document” button above this tab.',
        'approved_version' => 'This version is approved and its attachments are locked to preserve the legal review record.',
        'no_upload_permission' => 'You do not have permission to upload a legal document for this approved version.',
        'signed_version' => 'This version is signed. Legal documents are attached before signatures are recorded; create an amendment for a legal change.',
        'superseded_version' => 'This version has been replaced. Use the latest contract version.',
    ],
];
