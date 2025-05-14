<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\Type\Generic;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

class EnumExtension extends RuleExtension
{
    public function shouldHandle(mixed $rule): bool
    {
        return $rule instanceof Generic
            && count($rule->templateTypes) === 1
            && $rule->isInstanceOf(Enum::class);
    }

    public function handle(Generator\Types\Type $previousType, mixed $rule): Generator\Types\Type
    {
        $typeMapper = new RulesMapper($this->openApiTransformer);

        $class = $this->getNormalizedClassName($rule->templateTypes[0]->value);

        if (!class_exists($class)) {
            return $previousType;
        }

        return $typeMapper->enum($previousType, new Enum($class));
    }

    private function getNormalizedClassName(string $value): string
    {
        return Str::start($value, '\\');
    }
}
