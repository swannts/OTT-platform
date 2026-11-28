<?php

namespace App\Support\OpenApi;

use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class OpenApiExamplesTransformer
{
    public function __invoke(OpenApi $openApi): void
    {
        $curated = config('openapi_examples.operations', []);
        $errorTemplates = config('openapi_examples.error_responses', []);

        foreach ($openApi->paths as $pathItem) {
            if ($pathItem instanceof Reference) {
                continue;
            }

            foreach ($pathItem->operations as $operation) {
                $key = strtoupper($operation->method).' '.ltrim($pathItem->path, '/');
                $override = $curated[$key] ?? null;

                if (is_array($override) && empty($operation->summary) && ! empty($override['summary'])) {
                    $operation->summary($override['summary']);
                }

                $this->applyRequestExample($operation, $openApi->components, $override['request'] ?? null);
                $this->applyResponseExamples($operation, $openApi->components, $override['responses'] ?? []);
                $this->ensureErrorExamples($operation, $errorTemplates);
            }
        }
    }

    private function applyRequestExample(Operation $operation, Components $components, mixed $curatedRequest): void
    {
        $requestBody = $operation->requestBodyObject;
        if (! $requestBody instanceof RequestBodyObject) {
            return;
        }

        foreach ($requestBody->content as $mime => $content) {
            if ($content instanceof Reference) {
                continue;
            }

            $mediaType = $content instanceof MediaType ? $content : new MediaType(schema: $content);

            if ($curatedRequest !== null) {
                $mediaType->setExample($curatedRequest);
                $requestBody->content[$mime] = $mediaType;

                continue;
            }

            if (! $mediaType->example instanceof MissingValue || ! empty($mediaType->examples)) {
                continue;
            }

            $example = $this->synthesizeFromSchema($mediaType->schema, $components);
            if ($example !== null) {
                $mediaType->setExample($example);
                $requestBody->content[$mime] = $mediaType;
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $curatedResponses
     */
    private function applyResponseExamples(Operation $operation, Components $components, array $curatedResponses): void
    {
        if (! is_array($operation->responses)) {
            return;
        }

        foreach ($operation->responses as $index => $response) {
            if ($response instanceof Reference) {
                continue;
            }

            $code = (int) $response->code;
            $curatedExample = $curatedResponses[$code] ?? $curatedResponses[(string) $code] ?? null;

            if (empty($response->content)) {
                $fallback = $curatedExample ?? ($code >= 200 && $code < 300 ? [
                    'status' => true,
                    'message' => 'Success',
                ] : null);

                if ($fallback !== null) {
                    $mediaType = (new MediaType)->setExample($fallback);
                    $response->addContent('application/json', $mediaType);
                    $operation->responses[$index] = $response;
                }

                continue;
            }

            foreach ($response->content as $mime => $content) {
                if ($content instanceof Reference) {
                    continue;
                }

                $mediaType = $content instanceof MediaType ? $content : new MediaType(schema: $content);

                if ($curatedExample !== null) {
                    $mediaType->setExample($curatedExample);
                    $response->content[$mime] = $mediaType;

                    continue;
                }

                if (! $mediaType->example instanceof MissingValue || ! empty($mediaType->examples)) {
                    continue;
                }

                $synthesized = $this->synthesizeFromSchema($mediaType->schema, $components);
                if ($synthesized === null && $code >= 200 && $code < 300) {
                    $synthesized = [
                        'status' => true,
                        'message' => 'Success',
                        'data' => new \stdClass,
                    ];
                }

                if ($synthesized !== null) {
                    $mediaType->setExample($synthesized);
                    $response->content[$mime] = $mediaType;
                }
            }
        }
    }

    /**
     * @param  array<int|string, array{description?: string, example?: mixed}>  $errorTemplates
     */
    private function ensureErrorExamples(Operation $operation, array $errorTemplates): void
    {
        $existingCodes = [];
        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && $response->code !== null) {
                $existingCodes[(int) $response->code] = true;
            }
        }

        $isSecured = $operation->security === null || $operation->security !== [];
        if ($isSecured && ! isset($existingCodes[401]) && isset($errorTemplates[401])) {
            $operation->addResponse($this->buildJsonErrorResponse(401, $errorTemplates[401]));
        }

        if ($operation->requestBodyObject !== null && ! isset($existingCodes[422]) && isset($errorTemplates[422])) {
            $operation->addResponse($this->buildJsonErrorResponse(422, $errorTemplates[422]));
        }
    }

    /**
     * @param  array{description?: string, example?: mixed}  $template
     */
    private function buildJsonErrorResponse(int $code, array $template): Response
    {
        $mediaType = (new MediaType)->setExample($template['example'] ?? ['status' => false, 'message' => 'Error']);

        return Response::make($code)
            ->setDescription($template['description'] ?? 'Error')
            ->addContent('application/json', $mediaType);
    }

    private function synthesizeFromSchema(Schema|Reference|Type|null $node, Components $components, string $fieldName = '', int $depth = 0): mixed
    {
        if ($node === null || $depth > 4) {
            return null;
        }

        if ($node instanceof Reference) {
            $resolved = $this->resolveSchemaReference($node, $components);

            return $resolved ? $this->synthesizeFromSchema($resolved, $components, $fieldName, $depth + 1) : null;
        }

        if ($node instanceof Schema) {
            return $this->synthesizeFromSchema($node->type, $components, $fieldName, $depth);
        }

        if (! $node->example instanceof MissingValue) {
            return $node->example;
        }

        if (! empty($node->enum)) {
            return $node->enum[0];
        }

        if ($node instanceof AllOf) {
            $merged = [];
            foreach ($node->items as $item) {
                $part = $this->synthesizeFromSchema($item, $components, $fieldName, $depth + 1);
                if (is_array($part)) {
                    $merged = array_merge($merged, $part);
                }
            }

            return empty($merged) ? null : $merged;
        }

        if ($node instanceof AnyOf) {
            foreach ($node->items as $item) {
                $candidate = $this->synthesizeFromSchema($item, $components, $fieldName, $depth + 1);
                if ($candidate !== null) {
                    return $candidate;
                }
            }

            return null;
        }

        if ($node instanceof ObjectType) {
            $result = [];
            foreach ($node->properties as $propName => $propType) {
                $result[$propName] = $this->synthesizeFromSchema($propType, $components, (string) $propName, $depth + 1);
            }

            if (empty($result)) {
                return $depth === 0 ? ['status' => true, 'message' => 'Success'] : new \stdClass;
            }

            return $result;
        }

        if ($node instanceof ArrayType) {
            $item = $this->synthesizeFromSchema($node->items, $components, $fieldName, $depth + 1);

            return $item === null ? [] : [$item];
        }

        if ($node instanceof BooleanType) {
            return $this->guessBoolean($fieldName);
        }

        if ($node instanceof IntegerType) {
            return $this->guessInteger($fieldName);
        }

        if ($node instanceof NumberType) {
            return $this->guessNumber($fieldName);
        }

        if ($node instanceof StringType) {
            return $this->guessString($fieldName, $node->format ?? '');
        }

        return null;
    }

    private function resolveSchemaReference(Reference $reference, Components $components): Schema|Reference|null
    {
        $name = $reference->fullName;
        if (isset($components->schemas[$name])) {
            return $components->schemas[$name];
        }

        $shortName = basename(str_replace('\\', '/', $name));

        return $components->schemas[$shortName] ?? null;
    }

    private function guessBoolean(string $field): bool
    {
        return ! str_contains(strtolower($field), 'deleted') && ! str_contains(strtolower($field), 'error');
    }

    private function guessInteger(string $field): int
    {
        $lower = strtolower($field);
        if ($lower === 'page') {
            return 1;
        }
        if ($lower === 'per_page' || $lower === 'limit') {
            return 15;
        }
        if (str_contains($lower, 'status')) {
            return 1;
        }
        if (str_contains($lower, 'rating')) {
            return 5;
        }

        return 1;
    }

    private function guessNumber(string $field): float
    {
        $lower = strtolower($field);
        if (str_contains($lower, 'amount') || str_contains($lower, 'price') || str_contains($lower, 'total')) {
            return 9.99;
        }
        if (str_contains($lower, 'rating')) {
            return 4.5;
        }

        return 1.0;
    }

    private function guessString(string $field, string $format): string
    {
        $lower = strtolower($field);
        if ($format === 'email' || str_contains($lower, 'email')) {
            return 'viewer@example.com';
        }
        if ($format === 'uuid' || str_contains($lower, 'uuid')) {
            return '0195a6c2-7f3e-7000-8000-123456789abc';
        }
        if ($format === 'date-time' || str_ends_with($lower, '_at')) {
            return '2026-11-27T12:00:00Z';
        }
        if ($format === 'date' || str_contains($lower, 'date')) {
            return '2026-11-27';
        }
        if ($format === 'uri' || str_contains($lower, 'url') || str_contains($lower, 'image') || str_contains($lower, 'poster')) {
            return 'https://cdn.example.com/media/sample.jpg';
        }
        if (str_contains($lower, 'password')) {
            return '<your-password>';
        }
        if (str_contains($lower, 'phone') || str_contains($lower, 'mobile')) {
            return '+15550192834';
        }
        if (str_contains($lower, 'device_id')) {
            return 'web-chrome-8f42a1c9';
        }
        if (str_contains($lower, 'platform')) {
            return 'web';
        }
        if (str_contains($lower, 'currency')) {
            return 'USD';
        }
        if (str_contains($lower, 'slug')) {
            return 'sample-item';
        }
        if (str_contains($lower, 'token')) {
            return '1|<redacted>';
        }
        if (str_contains($lower, 'message')) {
            return 'Success';
        }
        if (str_contains($lower, 'name') || str_contains($lower, 'title')) {
            return 'Sample Title';
        }

        return 'string';
    }
}
