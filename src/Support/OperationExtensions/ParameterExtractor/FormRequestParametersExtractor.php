<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Spatie\LaravelData\Contracts\BaseData;

class FormRequestParametersExtractor implements ParameterExtractor
{
    use GeneratesParametersFromRules;

    public function __construct(
        private PrettyPrinter $printer,
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $requestClassName = $this->getFormRequestClassName($routeInfo)) {
            return $parameterExtractionResults;
        }

        if (is_a($requestClassName, BaseData::class, true)) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = $this->extractFormRequestParameters($requestClassName, $routeInfo);

        return $parameterExtractionResults;
    }

    private function getFormRequestClassName(RouteInfo $routeInfo): ?string
    {
        if (! $reflectionMethod = $routeInfo->reflectionMethod()) {
            return null;
        }

        /** @var ReflectionParameter $requestParam */
        if (! $requestParam = collect($reflectionMethod->getParameters())->first($this->isCustomRequestParam(...))) {
            return null;
        }

        $requestClassName = $requestParam->getType()->getName();

        $reflectionClass = new ReflectionClass($requestClassName);

        // If the classname is actually an interface, it may be bound to the container.
        if (! $reflectionClass->isInstantiable() && app()->bound($requestClassName)) {
            $classInstance = app()->getBindings()[$requestClassName]['concrete'](app());
            $requestClassName = $classInstance::class;
        }

        return $requestClassName;
    }

    private function isCustomRequestParam(ReflectionParameter $reflectionParameter): bool
    {
        if (! $reflectionParameter->getType() instanceof ReflectionNamedType) {
            return false;
        }

        $className = $reflectionParameter->getType()->getName();

        return method_exists($className, 'rules');
    }

    public function extractFormRequestParameters(string $requestClassName, RouteInfo $routeInfo): ParametersExtractionResult
    {
        $classReflector = Infer\Reflector\ClassReflector::make($requestClassName);

        $phpDocReflector = SchemaClassDocReflector::createFromDocString($classReflector->getReflection()->getDocComment() ?: '');

        $schemaName = ($phpDocReflector->getTagValue('@ignoreSchema')->value ?? null) !== null
            ? null
            : $phpDocReflector->getSchemaName($requestClassName);

        $schemaNameAttribute = ($classReflector->getReflection()
            ->getAttributes(SchemaName::class)[0] ?? null)?->newInstance();

        $schemaName = $schemaNameAttribute
            ? $schemaNameAttribute->name
            : $schemaName;

        return new ParametersExtractionResult(
            parameters: $this->makeParametersFromStaticRules(
                node: (new NodeFinder)->find(
                    Arr::wrap($classReflector->getMethod('rules')->getAstNode()->stmts),
                    fn (Node $node) => $node instanceof Node\Expr\ArrayItem
                        && $node->key instanceof Node\Scalar\String_
                        && $node->getAttribute('parsedPhpDoc'),
                ),
                rules: $this->rules($requestClassName, $routeInfo),
                typeTransformer: $this->openApiTransformer,
                in: in_array(mb_strtolower($routeInfo->route->methods()[0]), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                    ? 'query'
                    : 'body',
            ),
            schemaName: $schemaName,
            description: $phpDocReflector->getDescription(),
        );
    }

    protected function rules(string $requestClassName, RouteInfo $routeInfo): KeyedArrayType
    {
        $infer = new Infer($index = new Infer\Scope\Index);
        $inferred = $infer->analyzeClass($requestClassName);

        $scope = new GlobalScope;
        $scope->index = $index;

        $methodDefinition = $inferred->getMethodDefinition(
            'rules',
            $scope,
        );

        return $methodDefinition->type->returnType;
    }
}
