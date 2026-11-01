<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Validation;

use CakeDisposableEmail\DisposableEmailRegistry;
use InvalidArgumentException;
use Wodzy\DisposableEmail\DomainSet;
use Wodzy\DisposableEmail\Exception\InvalidEmailException;

/**
 * Validation rules exposed through the `disposableEmail` provider.
 *
 * ```
 * $validator
 *     ->email('email')
 *     ->add('email', 'notDisposable', [
 *         'rule' => 'notDisposable',
 *         'provider' => 'disposableEmail',
 *         'message' => __('Disposable email addresses are not allowed.'),
 *     ]);
 * ```
 *
 * The methods are static because CakePHP calls class-name providers statically.
 */
final class DisposableEmailValidation
{
    /**
     * Passes when the email is not blocked.
     *
     * A value without "@" passes, so that the `email` rule reports it with the
     * right message. CakePHP's `email` rule accepts some addresses that the
     * library rejects (e.g. a local part over 64 characters): those are still
     * checked by their domain, so a disposable address cannot slip through.
     * Values that could hide several addresses or header data (whitespace,
     * control characters, `,;<>`, more than one "@") fail.
     *
     * @param mixed $value Value being validated.
     * @param array<string, mixed> $context Validation context (required by CakePHP's rule signature).
     * @return bool
     */
    public static function notDisposable(mixed $value, array $context = []): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (!str_contains($value, '@')) {
            return true;
        }

        $domain = self::lenientDomainOf($value);
        if ($domain === null) {
            return false;
        }

        try {
            return !DisposableEmailRegistry::get()->blocks('check@' . $domain);
        } catch (InvalidEmailException) {
            return false;
        }
    }

    /**
     * Passes only when the email belongs to one of the given domains (or one of their subdomains).
     *
     * ```
     * ->add('email', 'allowedDomain', [
     *     'rule' => ['allowedDomain', ['company.com', 'company.fr']],
     *     'provider' => 'disposableEmail',
     *     'message' => __('Please use your company email address.'),
     * ])
     * ```
     *
     * `'rule' => ['allowedDomain', 'company.com']` works too for a single domain.
     * The rule fails closed: only a strictly valid address whose domain is in
     * the list passes. There is no lenient parsing here, because a value like
     * "attacker@evil.test,jane@company.com" must never be accepted.
     *
     * @param mixed $value Value being validated.
     * @param mixed ...$domains Allowed domains (strings or lists of strings).
     * @return bool
     * @throws \InvalidArgumentException When no domain is given.
     */
    public static function allowedDomain(mixed $value, mixed ...$domains): bool
    {
        // CakePHP 5.1 always appends the validation context; newer versions only
        // do it when the last parameter is named "context". The context is an
        // associative array, a domain list never is, so drop it when present.
        $last = end($domains);
        if (is_array($last) && !array_is_list($last)) {
            array_pop($domains);
        }
        $allowed = new DomainSet(self::domainList($domains));

        if (!is_string($value)) {
            return false;
        }

        try {
            return $allowed->contains(DomainSet::domainOf($value));
        } catch (InvalidEmailException) {
            return false;
        }
    }

    /**
     * Reads the domain of an email address that may not be strictly valid. Only for blocking.
     *
     * Strictly valid addresses use the library's parser. Otherwise, a value made
     * of one "@" and no whitespace, control character or `,;<>` is read by the
     * text after its "@". Anything else returns null, and the caller fails.
     *
     * @param string $value Email address.
     * @return string|null Null when no domain can be read safely.
     */
    private static function lenientDomainOf(string $value): ?string
    {
        try {
            return DomainSet::domainOf($value);
        } catch (InvalidEmailException) {
            if (substr_count($value, '@') !== 1 || preg_match('/[\s,;<>\x00-\x1f\x7f]/', $value) === 1) {
                return null;
            }
            $domain = DomainSet::normalize(substr($value, (int)strrpos($value, '@') + 1));

            return $domain === '' ? null : $domain;
        }
    }

    /**
     * @param array<mixed> $args Rule arguments.
     * @return list<string>
     * @throws \InvalidArgumentException When the arguments are not domains.
     */
    private static function domainList(array $args): array
    {
        $domains = [];
        foreach ($args as $arg) {
            foreach (is_array($arg) ? $arg : [$arg] as $domain) {
                if (!is_string($domain) || trim($domain) === '') {
                    throw new InvalidArgumentException(
                        'The "allowedDomain" rule expects domain names, e.g. '
                        . '[\'rule\' => [\'allowedDomain\', [\'company.com\']]].',
                    );
                }
                $domains[] = $domain;
            }
        }

        if ($domains === []) {
            throw new InvalidArgumentException(
                'The "allowedDomain" rule needs at least one domain, e.g. '
                . '[\'rule\' => [\'allowedDomain\', [\'company.com\']]].',
            );
        }

        return $domains;
    }
}
