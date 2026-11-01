<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase\Validation;

use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;
use CakeDisposableEmail\CakeDisposableEmailPlugin;
use CakeDisposableEmail\DisposableEmailRegistry;
use CakeDisposableEmail\Validation\DisposableEmailValidation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use TestApp\Application;
use Wodzy\DisposableEmail\Blocklist;
use Wodzy\DisposableEmail\DomainSet;

class DisposableEmailValidationTest extends TestCase
{
    /**
     * Snapshot of `Validator::$_defaultProviders`, restored as-is after each test.
     */
    private mixed $defaultProviders;

    protected function setUp(): void
    {
        parent::setUp();
        DisposableEmailRegistry::set(Blocklist::fromDomains(['yopmail.com']));
        $this->defaultProviders = $this->defaultProviders()->getValue();
    }

    protected function tearDown(): void
    {
        DisposableEmailRegistry::reset();
        // CakePHP has no API to remove a default provider: restore the registry
        // so the provider registered by pluginBootstrap() does not leak into other tests.
        $this->defaultProviders()->setValue(null, $this->defaultProviders);
        parent::tearDown();
    }

    private function defaultProviders(): ReflectionProperty
    {
        return new ReflectionProperty(Validator::class, '_defaultProviders');
    }

    public function testNotDisposable(): void
    {
        $this->assertFalse(DisposableEmailValidation::notDisposable('john@yopmail.com'));
        $this->assertFalse(DisposableEmailValidation::notDisposable('john@sub.yopmail.com'));
        $this->assertTrue(DisposableEmailValidation::notDisposable('john@gmail.com'));
    }

    public function testNonStringValuesFail(): void
    {
        $this->assertFalse(DisposableEmailValidation::notDisposable(null));
        $this->assertFalse(DisposableEmailValidation::notDisposable(['john@gmail.com']));
    }

    public function testValueWithoutAtSignIsLeftToTheEmailRule(): void
    {
        $this->assertTrue(DisposableEmailValidation::notDisposable('not-an-email'));
    }

    /**
     * CakePHP's `email` rule accepts these addresses while the library rejects them:
     * the domain must still be checked, or a disposable address slips through.
     *
     * @return iterable<string, array{string}>
     */
    public static function looselyValidEmailProvider(): iterable
    {
        yield 'trailing newline' => ["jane@yopmail.com\n"];
        yield 'local part over 64 characters' => [str_repeat('a', 65) . '@yopmail.com'];
        yield 'surrounding spaces' => [' jane@yopmail.com '];
        yield 'upper case with trailing newline' => ["JANE@SUB.YOPMAIL.COM\n"];
    }

    #[DataProvider('looselyValidEmailProvider')]
    public function testNotDisposableChecksTheDomainOfLooselyValidEmails(string $email): void
    {
        $this->assertFalse(DisposableEmailValidation::notDisposable($email));
    }

    /**
     * Values that could hide several recipients or header data, behind a valid-looking last domain.
     *
     * @return iterable<string, array{string}>
     */
    public static function smugglingValueProvider(): iterable
    {
        yield 'no domain' => ['jane@'];
        yield 'comma separated list' => ['attacker@yopmail.com,jane@gmail.com'];
        yield 'semicolon separated list' => ['attacker@yopmail.com;jane@gmail.com'];
        yield 'space separated list' => ['attacker@yopmail.com jane@gmail.com'];
        yield 'angle brackets' => ['Jane <attacker@yopmail.com>@gmail.com'];
        yield 'header injection' => ["jane@gmail.com\nBcc: attacker@yopmail.com"];
        yield 'trailing newline on a safe domain' => ["jane@gmail.com\n"];
        yield 'NUL byte' => ["jane@gmail.com\0"];
        yield 'two at signs' => ['attacker@yopmail.com@gmail.com'];
    }

    #[DataProvider('smugglingValueProvider')]
    public function testNotDisposableFailsOnValuesThatCouldHideAnotherAddress(string $value): void
    {
        $this->assertFalse(DisposableEmailValidation::notDisposable($value));
    }

    public function testStrictlyValidQuotedLocalPartIsStillReadByItsDomain(): void
    {
        $this->assertTrue(DisposableEmailValidation::notDisposable('"jane.doe"@gmail.com'));
        $this->assertFalse(DisposableEmailValidation::notDisposable('"a@b"@yopmail.com'));
        // Not strictly valid for PHP, and contains whitespace: refused rather than guessed.
        $this->assertFalse(DisposableEmailValidation::notDisposable('"jane doe"@gmail.com'));
    }

    public function testCakeEmailRulePlusNotDisposableRejectsLooselyValidDisposableEmails(): void
    {
        $validator = (new Validator())
            ->email('email')
            ->add('email', 'notDisposable', ['rule' => [DisposableEmailValidation::class, 'notDisposable']]);

        foreach (["jane@yopmail.com\n", str_repeat('a', 65) . '@yopmail.com'] as $email) {
            $errors = $validator->validate(['email' => $email]);
            $this->assertArrayHasKey('notDisposable', $errors['email'] ?? [], json_encode($email) ?: '');
        }
    }

    public function testNeverBlockedDomainsPass(): void
    {
        DisposableEmailRegistry::set(new Blocklist(
            new DomainSet(['yopmail.com']),
            new DomainSet(['partner.yopmail.com']),
        ));

        $this->assertFalse(DisposableEmailValidation::notDisposable('a@yopmail.com'));
        $this->assertTrue(DisposableEmailValidation::notDisposable('a@partner.yopmail.com'));
    }

    public function testPluginRegistersTheDefaultProvider(): void
    {
        $app = new Application(CONFIG);
        $app->bootstrap();
        $app->pluginBootstrap();

        $validator = (new Validator())
            ->email('email')
            ->add('email', 'notDisposable', [
                'rule' => 'notDisposable',
                'provider' => CakeDisposableEmailPlugin::PROVIDER,
                'message' => 'Disposable email addresses are not allowed.',
            ]);

        $this->assertSame([], $validator->validate(['email' => 'john@gmail.com']));
        $this->assertSame(
            ['email' => ['notDisposable' => 'Disposable email addresses are not allowed.']],
            $validator->validate(['email' => 'john@yopmail.com']),
        );
        $errors = $validator->validate(['email' => 'not-an-email']);
        $this->assertArrayHasKey('email', $errors['email']);
        $this->assertArrayNotHasKey('notDisposable', $errors['email']);
    }

    public function testAllowedDomainAcceptsOnlyListedDomains(): void
    {
        $this->assertTrue(DisposableEmailValidation::allowedDomain('jane@company.com', ['company.com']));
        $this->assertTrue(DisposableEmailValidation::allowedDomain('jane@COMPANY.com', ['company.com']));
        $this->assertTrue(DisposableEmailValidation::allowedDomain('jane@paris.company.com', ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain('jane@gmail.com', ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain('jane@company.com.evil.test', ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain('jane@notcompany.com', ['company.com']));
    }

    public function testAllowedDomainAcceptsSeveralDomainsInEveryForm(): void
    {
        $this->assertTrue(DisposableEmailValidation::allowedDomain('a@company.fr', ['company.com', 'company.fr']));
        $this->assertTrue(DisposableEmailValidation::allowedDomain('a@company.fr', 'company.com', 'company.fr'));
        $this->assertTrue(DisposableEmailValidation::allowedDomain('a@company.com', 'company.com'));
    }

    public function testAllowedDomainIgnoresATrailingValidationContext(): void
    {
        // CakePHP 5.1 appends the context as the last positional argument.
        $context = ['data' => [], 'newRecord' => true, 'field' => 'email', 'providers' => []];

        $this->assertTrue(DisposableEmailValidation::allowedDomain('a@company.com', ['company.com'], $context));
        $this->assertTrue(DisposableEmailValidation::allowedDomain('a@company.fr', 'company.com', 'company.fr', $context));
        $this->assertFalse(DisposableEmailValidation::allowedDomain('a@gmail.com', ['company.com'], $context));

        $this->expectException(InvalidArgumentException::class);
        DisposableEmailValidation::allowedDomain('a@company.com', $context);
    }

    public function testAllowedDomainFailsClosed(): void
    {
        $this->assertFalse(DisposableEmailValidation::allowedDomain('not-an-email', ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain('jane@', ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain(null, ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain("jane@gmail.com\n", ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain(str_repeat('a', 65) . '@gmail.com', ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain("jane@company.com\nBcc: x", ['company.com']));
        $this->assertFalse(DisposableEmailValidation::allowedDomain("jane@company.com\n", ['company.com']), 'strict parsing only');
        $this->assertFalse(DisposableEmailValidation::allowedDomain(str_repeat('a', 65) . '@company.com', ['company.com']));
    }

    #[DataProvider('smugglingAllowListProvider')]
    public function testAllowedDomainRejectsValuesEndingWithAnAllowedDomain(string $value): void
    {
        $this->assertFalse(DisposableEmailValidation::allowedDomain($value, ['company.com']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function smugglingAllowListProvider(): iterable
    {
        yield 'comma separated list' => ['attacker@evil.test,jane@company.com'];
        yield 'semicolon separated list' => ['attacker@evil.test;jane@company.com'];
        yield 'space separated list' => ['attacker@evil.test jane@company.com'];
        yield 'angle brackets' => ['Jane <attacker@evil.test>@company.com'];
        yield 'two at signs' => ['attacker@evil.test@company.com'];
        yield 'header injection' => ["attacker@evil.test\nCc: jane@company.com"];
    }

    public function testCakeEmailRulePlusAllowedDomainRejectsLooselyValidOutsiders(): void
    {
        $validator = (new Validator())
            ->email('email')
            ->add('email', 'allowedDomain', ['rule' => [DisposableEmailValidation::class, 'allowedDomain'], 'pass' => [['company.com']]]);

        foreach (["jane@gmail.com\n", str_repeat('a', 65) . '@gmail.com'] as $email) {
            $errors = $validator->validate(['email' => $email]);
            $this->assertArrayHasKey('allowedDomain', $errors['email'] ?? [], json_encode($email) ?: '');
        }
    }

    /**
     * @param array<mixed> $args
     */
    #[DataProvider('invalidAllowedDomainArgsProvider')]
    public function testAllowedDomainNeedsDomains(array $args): void
    {
        $this->expectException(InvalidArgumentException::class);

        DisposableEmailValidation::allowedDomain('a@company.com', ...$args);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidAllowedDomainArgsProvider(): iterable
    {
        yield 'rule used without a list' => [[]];
        yield 'empty list' => [[[]]];
        yield 'empty string' => [[['  ']]];
        yield 'non string' => [[[42]]];
    }

    public function testAllowedDomainThroughACakeValidator(): void
    {
        $app = new Application(CONFIG);
        $app->bootstrap();
        $app->pluginBootstrap();

        $validator = (new Validator())
            ->email('email')
            ->add('email', 'allowedDomain', [
                'rule' => ['allowedDomain', ['company.com', 'company.fr']],
                'provider' => CakeDisposableEmailPlugin::PROVIDER,
                'message' => 'Please use your company email address.',
            ]);

        $this->assertSame([], $validator->validate(['email' => 'jane@company.fr']));
        $this->assertSame(
            ['email' => ['allowedDomain' => 'Please use your company email address.']],
            $validator->validate(['email' => 'jane@gmail.com']),
        );
    }
}
