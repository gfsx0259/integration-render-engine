<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

final readonly class Lead
{
    /**
     * @param array<string, string> $subs
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $ip,
        public string $countryCode,
        public string $password = '',
        public string $offerId = '',
        public array $subs = [],
    ) {}
}
