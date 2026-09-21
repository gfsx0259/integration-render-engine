<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

enum Transport: string
{
    case Svix = 'svix';
    case Http = 'http';
}
