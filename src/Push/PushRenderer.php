<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

use Enthusiast\IntegrationRenderEngine\ConnectionTemplate;
use Enthusiast\IntegrationRenderEngine\Macros;
use Enthusiast\IntegrationRenderEngine\StaticValues;

final readonly class PushRenderer
{
    public function __construct(
        private ConnectionTemplate $template = new ConnectionTemplate(),
    ) {}

    /**
     * @param array<string, mixed> $bodyTemplate
     * @param array<string, mixed> $headersTemplate
     * @param array<string, mixed> $fieldValues
     * @param array<string, mixed> $leadVars
     */
    public function render(
        array $bodyTemplate,
        array $headersTemplate,
        array $fieldValues,
        BodyEncoding $encoding,
        array $leadVars,
    ): PushMessage {
        $static = StaticValues::normalizeMap($this->template->render(
            Macros::expandAll($fieldValues, $leadVars),
            $leadVars,
            [],
        ));

        return new PushMessage(
            $this->template->dropEmpty($this->template->render($bodyTemplate, $leadVars, $static)),
            $this->template->renderHeaders(
                array_filter(
                    $headersTemplate,
                    fn (mixed $value): bool => $this->template->dependsOnLead($value, $fieldValues),
                ),
                $leadVars,
                $static,
            ),
            $encoding,
        );
    }

    /**
     * @param array<string, mixed> $headersTemplate
     * @param array<string, mixed> $fieldValues
     * @return array<string, string>
     */
    public function endpointHeaders(array $headersTemplate, array $fieldValues): array
    {
        return $this->template->renderHeaders(
            array_filter(
                $headersTemplate,
                fn (mixed $value): bool => !$this->template->dependsOnLead($value, $fieldValues),
            ),
            [],
            $fieldValues,
        );
    }
}
