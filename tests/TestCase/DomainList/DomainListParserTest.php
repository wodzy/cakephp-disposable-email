<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase\DomainList;

use Cake\TestSuite\TestCase;
use CakeDisposableEmail\DomainList\DomainListParser;

class DomainListParserTest extends TestCase
{
    public function testParseCleansAndFiltersTheList(): void
    {
        $body = "# comment line\n"
            . "Zeta.TEST\r\n"
            . "  alpha.test  # trailing comment\n"
            . "alpha.test\n"
            . "\n"
            . "gmail.com\n"
            . "localhost\n"
            . "not a domain\n"
            . "-bad-.test\n"
            . "xn--mnchen-3ya.de\n";

        $result = (new DomainListParser())->parse($body);

        $this->assertSame(['alpha.test', 'xn--mnchen-3ya.de', 'zeta.test'], $result->domains);
        $this->assertSame(['gmail.com'], $result->skipped);
        $this->assertSame(3, $result->invalidLines);
    }

    public function testEveryProtectedProviderIsSkipped(): void
    {
        $result = (new DomainListParser())->parse(implode("\n", DomainListParser::PROTECTED_DOMAINS));

        $this->assertSame([], $result->domains);
        $this->assertSame(DomainListParser::PROTECTED_DOMAINS, $result->skipped);
    }

    public function testEmptyBody(): void
    {
        $result = (new DomainListParser())->parse('');

        $this->assertSame([], $result->domains);
        $this->assertSame(0, $result->invalidLines);
    }
}
