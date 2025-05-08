<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;

class StaticRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private ClassReflector $classReflector,
    ) {
    }

    public function handle(): array
    {
        $rules = $this->rules($this->classReflector->className);

        $rules = (new Collection($rules->items))
            ->mapWithKeys(function (ArrayItemType_ $type) {
                $key = $type->key ?? null;

                if ($type->value instanceof LiteralStringType) {
                    return [(string) $key => $type->value->value];
                }

                if ($type->value instanceof KeyedArrayType) {
                    $unpacked = $this->rulesFromKeyedArray($type->value);

                    return [(string) $key => $unpacked];
                }

                return [(string) $key => 'unsupported'];
            });

        return $rules->all();
    }

    private function rulesFromKeyedArray(KeyedArrayType $value): array
    {
        return (new Collection($value->items))
            ->map(function (ArrayItemType_ $type) {
                if ($type->value instanceof LiteralStringType) {
                    return $type->value->value;
                }

                return $type->value;
            })
            ->all();
    }

    /**
     * @param string $requestClassName
     * @return \Dedoc\Scramble\Support\Type\KeyedArrayType
     */
    protected function rules(string $requestClassName): KeyedArrayType
    {
        $infer = app(Infer::class);
        $inferred = $infer->analyzeClass($requestClassName);

        $scope = app(GlobalScope::class);

        $methodDefinition = $inferred->getMethodDefinition('rules', $scope);

        return $methodDefinition->type->returnType;
    }
}
