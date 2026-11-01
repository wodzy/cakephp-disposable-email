<?php
declare(strict_types=1);

namespace CakeDisposableEmail\DomainList;

/**
 * Parses a "one domain per line" list, such as the disposable-email-domains project.
 *
 * Comments (`# ...`), blank lines and lines that are not domain names are
 * ignored. Mainstream mailbox providers are always skipped, whatever the
 * source says: a bad upstream entry must never block real users.
 */
final class DomainListParser
{
    /**
     * Mainstream providers that must never end up on a downloaded list.
     *
     * @var list<string>
     */
    public const PROTECTED_DOMAINS = [
        'aol.com',
        'free.fr',
        'gmail.com',
        'gmx.com',
        'gmx.de',
        'googlemail.com',
        'hotmail.com',
        'hotmail.fr',
        'icloud.com',
        'laposte.net',
        'live.com',
        'mail.com',
        'mail.ru',
        'me.com',
        'orange.fr',
        'outlook.com',
        'outlook.fr',
        'proton.me',
        'protonmail.com',
        'sfr.fr',
        'web.de',
        'yahoo.com',
        'yahoo.fr',
        'yandex.com',
        'yandex.ru',
        'zoho.com',
    ];

    private const DOMAIN_PATTERN = '/^(?=.{4,253}$)'
        . '(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/';

    /**
     * @param string $body Raw list.
     * @return \CakeDisposableEmail\DomainList\ParsedDomainList
     */
    public function parse(string $body): ParsedDomainList
    {
        $protected = array_fill_keys(self::PROTECTED_DOMAINS, true);
        $domains = [];
        $skipped = [];
        $invalidLines = 0;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $domain = strtolower(trim((string)preg_replace('/#.*/', '', $line)));
            if ($domain === '') {
                continue;
            }
            if (preg_match(self::DOMAIN_PATTERN, $domain) !== 1) {
                $invalidLines++;
                continue;
            }
            if (isset($protected[$domain])) {
                $skipped[$domain] = true;
                continue;
            }
            $domains[$domain] = true;
        }

        return new ParsedDomainList(self::sortedKeys($domains), self::sortedKeys($skipped), $invalidLines);
    }

    /**
     * @param array<string, true> $map Domains as keys.
     * @return list<string>
     */
    private static function sortedKeys(array $map): array
    {
        $keys = array_map('strval', array_keys($map));
        sort($keys);

        return $keys;
    }
}
