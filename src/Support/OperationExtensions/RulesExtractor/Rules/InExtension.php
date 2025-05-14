<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\In;
use Webmozart\Assert\Assert;

class InExtension extends RuleExtension
{
    /**
     * Determine whether this extension should handle the given type.
     *
     * @param \Dedoc\Scramble\Support\Type\Type $rule
     * @return bool
     */
    public function shouldHandle(mixed $rule): bool
    {
        return $rule instanceof Generic
            && count($rule->templateTypes) === 1
            && $rule->isInstanceOf(In::class);
    }

    /**
     * Handle the given type and return the transformed OpenApi type.
     *
     * @param \Dedoc\Scramble\Support\Generator\Types\Type $previousType
     * @param \Dedoc\Scramble\Support\Type\Type $rule
     * @return \Dedoc\Scramble\Support\Generator\Types\Type
     */
    public function handle(OpenApiType $previousType, mixed $rule): OpenApiType
    {
        Assert::isInstanceOf($rule, Generic::class);

        $typeMapper = new RulesMapper($this->openApiTransformer);

        return $typeMapper->in($previousType, $this->getNormalizedValues($rule->templateTypes[0]));
    }

    /**
     * Get the normalized values from the In rule.
     *
     * @param \Dedoc\Scramble\Support\Type\KeyedArrayType $rule
     * @return array<string>
     */
    private function getNormalizedValues(KeyedArrayType $rule): array
    {
        return (new Collection($rule->items))
            ->map(fn (ArrayItemType_ $itemType) => $itemType->value)
            ->filter(fn (Type $t) => $t instanceof LiteralStringType)
            ->map(fn (LiteralStringType $t) => $t->value)
            ->values()
            ->all();
    }
}
