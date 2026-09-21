<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests\Push;

use Enthusiast\IntegrationRenderEngine\Push\PushVerdict;
use Enthusiast\IntegrationRenderEngine\Push\ResponseSpecParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseSpecParserTest extends TestCase
{
    private ResponseSpecParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ResponseSpecParser();
    }

    public function testEmptySpecIsNull(): void
    {
        self::assertNull($this->parser->hydrateAndValidate(null));
        self::assertNull($this->parser->hydrateAndValidate([]));
    }

    public function testInvalidOkRuleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->hydrateAndValidate(['ok' => ['path' => 'status']]);
    }

    public function testWithoutSpecAnyTwoHundredIsAccepted(): void
    {
        $verdict = $this->parser->verdict(null, 200, '{"whatever": 1}');

        self::assertSame(PushVerdict::ACCEPTED, $verdict->outcome);
        self::assertNull($verdict->externalId);
    }

    public function testNonTwoHundredIsErrorWithBodyAsMessage(): void
    {
        $verdict = $this->parser->verdict(null, 500, 'internal error');

        self::assertSame(PushVerdict::ERROR, $verdict->outcome);
        self::assertSame(500, $verdict->code);
        self::assertSame('internal error', $verdict->error);
    }

    public function testTrackboxAcceptedReadsIdsAndAutologin(): void
    {
        $spec = $this->parser->hydrateAndValidate([
            'ok' => ['path' => 'status', 'eq' => true],
            'external_id' => 'data.leadId',
            'autologin' => 'data.autoLoginUrl',
            'error' => 'errorMessage',
        ]);
        $verdict = $this->parser->verdict($spec, 200, json_encode([
            'status' => true,
            'errorMessage' => null,
            'data' => ['leadId' => 'mp-1', 'autoLoginUrl' => '/autologin/mp-1'],
        ]));

        self::assertTrue($verdict->accepted());
        self::assertSame('mp-1', $verdict->externalId);
        self::assertSame('/autologin/mp-1', $verdict->autologin);
        self::assertNull($verdict->error);
    }

    public function testTwoHundredWithFalseStatusIsRejected(): void
    {
        $spec = $this->parser->hydrateAndValidate([
            'ok' => ['path' => 'status', 'eq' => true],
            'error' => 'errorMessage',
        ]);
        $verdict = $this->parser->verdict($spec, 200, '{"status": false, "errorMessage": "duplicate"}');

        self::assertTrue($verdict->rejected());
        self::assertSame('duplicate', $verdict->error);
    }

    public function testStringStatusMatchesCaseInsensitively(): void
    {
        $spec = $this->parser->hydrateAndValidate(['ok' => ['path' => 'result', 'eq' => 'success']]);

        self::assertTrue($this->parser->verdict($spec, 200, '{"result": "SUCCESS"}')->accepted());
        self::assertTrue($this->parser->verdict($spec, 200, '{"result": "fail"}')->rejected());
    }

    public function testBooleanRuleAcceptsStringForms(): void
    {
        $spec = $this->parser->hydrateAndValidate(['ok' => ['path' => 'success', 'eq' => true]]);

        self::assertTrue($this->parser->verdict($spec, 200, '{"success": "true"}')->accepted());
        self::assertTrue($this->parser->verdict($spec, 200, '{"success": "0"}')->rejected());
    }

    public function testNonJsonBodyWithSpecIsRejected(): void
    {
        $spec = $this->parser->hydrateAndValidate(['ok' => ['path' => 'ok', 'eq' => 1]]);
        $verdict = $this->parser->verdict($spec, 200, '<html>login</html>');

        self::assertTrue($verdict->rejected());
        self::assertSame('<html>login</html>', $verdict->error);
    }
}
