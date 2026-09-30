<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests\Push;

use Enthusiast\IntegrationRenderEngine\LeadVars;
use Enthusiast\IntegrationRenderEngine\Push\BodyEncoding;
use Enthusiast\IntegrationRenderEngine\Push\PushMessage;
use Enthusiast\IntegrationRenderEngine\Push\PushRenderer;
use PHPUnit\Framework\TestCase;

final class PushRendererTest extends TestCase
{
    private const array BODY = [
        'email' => '{{lead.email}}',
        'phone' => '{{lead.phone_national}}',
        'funnel' => '{{static.FUNNEL}}',
        'sub' => '{{static.AFF_SUB}}',
        'click' => '{{lead.lead_id}}',
    ];

    private const array HEADERS = [
        'Api-Key' => '{{static.API_KEY}}',
        'X-Click' => '{{static.CLICK}}',
    ];

    private const array VALUES = [
        'API_KEY' => 'secret',
        'FUNNEL' => '',
        'AFF_SUB' => '{aff_sub4}_{funnelName}',
        'CLICK' => '{{lead.lead_id}}',
    ];

    public function testRendersBodyAndOnlyLeadDependentHeaders(): void
    {
        $message = (new PushRenderer())->render(self::BODY, self::HEADERS, self::VALUES, BodyEncoding::Json, $this->lead());

        self::assertSame([
            'email' => 'test@example.com',
            'phone' => '9012345678',
            'sub' => 'ZvPlatform',
            'click' => 'pub-1',
        ], $message->payload);
        self::assertSame(['X-Click' => 'pub-1'], $message->headers);
        self::assertSame(BodyEncoding::Json, $message->encoding);
    }

    public function testEndpointHeadersAreTheRest(): void
    {
        self::assertSame(['Api-Key' => 'secret'], (new PushRenderer())->endpointHeaders(self::HEADERS, self::VALUES));
    }

    public function testJsonSendsOnlyHeaders(): void
    {
        $message = new PushMessage(['a' => '1'], ['X-Click' => 'pub-1'], BodyEncoding::Json);

        self::assertSame(['headers' => ['X-Click' => 'pub-1']], $message->transformationParams());
        self::assertSame([], (new PushMessage(['a' => '1'], [], BodyEncoding::Json))->transformationParams());
    }

    public function testFormEncodesBodyAndSetsContentType(): void
    {
        $message = new PushMessage(['a' => '1', 'b' => 'x y'], [], BodyEncoding::Form);

        self::assertSame([
            'rawPayload' => 'a=1&b=x+y',
            'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
        ], $message->transformationParams());
    }

    public function testQueryMovesBodyToQueryString(): void
    {
        $message = new PushMessage(['a' => '1'], [], BodyEncoding::Query);

        self::assertSame(['rawPayload' => '', 'query' => 'a=1'], $message->transformationParams());
    }

    /**
     * @return array<string, mixed>
     */
    private function lead(): array
    {
        return LeadVars::build([
            'email' => 'test@example.com',
            'phone' => '+81 90-1234-5678',
            'country_code' => 'jp',
            'aff_sub4' => 'ZvPlatform',
        ], ['lead_id' => 'pub-1']);
    }
}
