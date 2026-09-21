<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

enum BodyEncoding: string
{
    case Json = 'json';
    case Form = 'form';
    case Query = 'query';
}
