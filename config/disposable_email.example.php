<?php

use CakeDisposableEmail\DisposableEmailConfig;

/**
 * CakeDisposableEmail configuration.
 *
 * Copy the `DisposableEmail` key into your `config/app_local.php` (or `config/app.php`)
 * and keep only the options you change. Every value below is the default
 * (see `DisposableEmailConfig::defaults()`). An unknown option throws an exception.
 */
return [
    'DisposableEmail' => [
        // Use the curated list shipped with wodzy/disposable-email.
        'useBundledList' => true,

        // Extra domains to block. Subdomains are blocked too ("foo.example.com").
        // Never add a public suffix such as "co.uk" or "github.io".
        'domains' => [],

        // Domains that are never blocked by the `notDisposable` rule, even when a list
        // contains them or one of their parent domains. Subdomains are covered too.
        'neverBlock' => [],

        // Extra JSON files with the shape {"domains": ["a.com", ...]}.
        'files' => [],

        // File written by `bin/cake disposable_email update` and loaded when it exists.
        // Set to null to disable the downloaded list.
        'downloadedListPath' => CONFIG . DisposableEmailConfig::DOWNLOADED_LIST_FILE,

        // Source used by `bin/cake disposable_email update` (one domain per line).
        'updateUrl' => DisposableEmailConfig::DEFAULT_UPDATE_URL,

        // The update is refused (and the current file kept) below this count. Must be >= 1.
        'minimumDomains' => 1000,

        // Cache configuration storing the merged list (see config/app.php `Cache`).
        // Set to null to rebuild the list on every request.
        'cacheConfig' => 'default',
    ],
];
