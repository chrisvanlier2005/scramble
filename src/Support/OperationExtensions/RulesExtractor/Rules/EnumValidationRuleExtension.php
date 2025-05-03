<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\Rules\Enum;

class EnumValidationRuleExtension extends ValidationRuleExtension
{
    public function shouldHandle(Type $rule): bool
    {
        return $rule instanceof Generic
            && count($rule->templateTypes) === 1
            && $rule->isInstanceOf(Enum::class);
    }

    public function handle(OpenApiType $previousType, Type $rule): OpenApiType
    {
        if (
            ! $rule instanceof Generic
            || ! $rule->isInstanceOf(Enum::class)
            || count($rule->templateTypes) !== 1
        ) {
            return $previousType;
        }

        $typeMapper = new RulesMapper($this->openApiTransformer);

        $enum = new Enum("\\" . $rule->templateTypes[0]->value);

        return $typeMapper->enum($previousType, $enum);
    }
}
