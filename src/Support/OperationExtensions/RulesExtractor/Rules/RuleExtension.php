<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Type;

abstract class RuleExtension
{
    public function __construct(
        public TypeTransformer $openApiTransformer,
    ) {}

    abstract public function shouldHandle(mixed $rule): bool;

    abstract public function handle(OpenApiType $previousType, mixed $rule): OpenApiType;
}
