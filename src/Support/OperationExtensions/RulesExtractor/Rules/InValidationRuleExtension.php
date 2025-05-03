<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\Rules\In;

class InValidationRuleExtension extends ValidationRuleExtension
{
    public function shouldHandle(Type $rule): bool
    {
        return $rule instanceof Generic
            && count($rule->templateTypes) === 1
            && $rule->isInstanceOf(In::class);
    }

    public function handle(OpenApiType $previousType, Type $rule): OpenApiType
    {
        if (
            ! $rule instanceof Generic
            || ! $rule->isInstanceOf(In::class)
            || count($rule->templateTypes) !== 1
        ) {
            return $previousType;
        }

        $typeMapper = new RulesMapper(
            $this->openApiTransformer,
        );

        return $typeMapper->in($previousType, $this->getNormalizedValues($rule->templateTypes[0]));
    }

    private function getNormalizedValues(KeyedArrayType $rule)
    {
        return collect($rule->items)
            ->map(fn (ArrayItemType_ $itemType) => $itemType->value)
            ->filter(fn (Type $t) => $t instanceof LiteralStringType)
            ->map(fn (LiteralStringType $t) => $t->value)
            ->values()
            ->all();
    }
}
