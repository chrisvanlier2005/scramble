<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\Rules\File;

class FileRuleExtension extends ValidationRuleExtension
{
    public function shouldHandle(mixed $rule): bool
    {
        return $rule instanceof Generic
            && $rule->isInstanceOf(File::class)
            && count($rule->templateTypes) === 0;
    }

    public function handle(OpenApiType $previousType, mixed $rule): OpenApiType
    {
        return (new RulesMapper($this->openApiTransformer))->file($previousType);
    }
}
