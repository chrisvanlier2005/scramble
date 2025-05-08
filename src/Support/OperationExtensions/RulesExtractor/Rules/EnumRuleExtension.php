<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\Rules\Enum;

class EnumRuleExtension extends ValidationRuleExtension
{
    public function shouldHandle(mixed $rule): bool
    {
        return $rule instanceof Generic
            && count($rule->templateTypes) === 1
            && $rule->isInstanceOf(Enum::class);
    }

    public function handle(OpenApiType $previousType, mixed $rule): OpenApiType
    {
        if (
            ! $rule instanceof Generic
            || ! $rule->isInstanceOf(Enum::class)
            || count($rule->templateTypes) !== 1
        ) {
            return $previousType;
        }

        $typeMapper = new RulesMapper($this->openApiTransformer);

        $class = str_starts_with($rule->templateTypes[0]->value, '\\')
            ? $rule->templateTypes[0]->value
            : "\\" . $rule->templateTypes[0]->value;

        $enum = new Enum($class);

        return $typeMapper->enum($previousType, $enum);
    }
}
