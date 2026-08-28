<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

enum TemplateSource: string
{
    case Lead = 'lead';
    case Static = 'static';
}
