# integration-render-engine

Renders adapter templates with `{{lead.*}}` and `{{static.*}}` placeholders.

- `{{lead.email}}` — from the lead payload
- `{{static.api_token}}` — from connection `header_values` / `field_values`
- literals without placeholders stay as-is (`"Content-Type": "application/json"`)

Used by API (headers at connection save) and `worker-integration` (body at publish).

```php
use Enthusiast\IntegrationRenderEngine\ConnectionTemplate;
use Enthusiast\IntegrationRenderEngine\TemplateSource;

$engine = new ConnectionTemplate();

$payload = $engine->render(
    ['email' => '{{lead.email}}', 'aff_id' => '{{static.affiliate_id}}'],
    ['email' => 'a@b.c'],
    ['affiliate_id' => '42'],
);
// ['email' => 'a@b.c', 'aff_id' => '42']

$headers = $engine->renderHeaders(
    ['Authorization' => 'Bearer {{static.api_token}}'],
    [],
    ['api_token' => 'secret'],
);

$staticKeys = $engine->keys($bodyTemplate, TemplateSource::Static);
```
