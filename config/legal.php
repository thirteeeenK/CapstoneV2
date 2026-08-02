<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legal Document Versions
    |--------------------------------------------------------------------------
    |
    | Bumping a version will require users who registered under the old version
    | to re-accept the updated document before continuing to use features that
    | depend on that consent.
    |
    */

    'documents' => [
        'terms' => [
            'version' => '1.0',
            'effective_date' => '2026-08-02',
        ],
        'privacy' => [
            'version' => '1.0',
            'effective_date' => '2026-08-02',
        ],
        'ai_disclosure' => [
            'version' => '1.0',
            'effective_date' => '2026-08-02',
        ],
    ],
];
