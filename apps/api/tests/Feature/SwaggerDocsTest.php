<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SwaggerDocsTest extends TestCase
{
    public function test_swagger_docs_are_restricted_without_gate_in_non_local_env(): void
    {
        $this->get(route('docs.swagger.index'))->assertForbidden();
        $this->getJson(route('scramble.docs.document'))->assertForbidden();
    }

    public function test_swagger_ui_and_openapi_document_render_with_examples_when_authorized(): void
    {
        Gate::define('viewApiDocs', fn ($user = null) => true);

        $ui = $this->get(route('docs.swagger.index'));
        $ui->assertOk();
        $ui->assertSee('SwaggerUIBundle', false);
        $ui->assertSee(route('scramble.docs.document'), false);

        $doc = $this->getJson(route('scramble.docs.document'));
        $doc->assertOk();

        $spec = $doc->json();
        $this->assertSame('NOOR OTT API', $spec['info']['title'] ?? null);
        $this->assertNotEmpty($spec['paths']['/login']['post']['requestBody']['content']['application/json']['example'] ?? null);
        $this->assertNotEmpty($spec['paths']['/login']['post']['responses']['200']['content']['application/json']['example'] ?? null);
        $this->assertNotEmpty($spec['paths']['/login']['post']['responses']['422']['content']['application/json']['example'] ?? null);
    }
}
