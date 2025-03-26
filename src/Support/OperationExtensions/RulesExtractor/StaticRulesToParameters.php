<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class StaticRulesToParameters
{
    private bool $mergeDotNotatedKeys = true;

    public function __construct(
        private KeyedArrayType $rules,
        array $validationNodesResults,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {
    }

    public function mergeDotNotatedKeys(bool $value)
    {
        $this->mergeDotNotatedKeys = $value;

        return $this;
    }

    public function handle()
    {
        return collect($this->rules->items)
            // TODO: handle confirmed.
            ->map(function (ArrayItemType_ $rules) {
                return (new StaticRulesToParameter(
                    name: $rules->key,
                    rules: $rules->value,
                    docNode: $rules->attributes()[0] ?? null,
                    openApiTransformer: $this->openApiTransformer,
                ))->generate();
            })
            ->filter()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->values()
            ->all();
    }

    private function handleConfirmed(Collection $rules)
    {
        $confirmedParamNameRules = $rules
            ->map(fn ($rules, $name) => [$name, Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules)])
            ->filter(fn ($nameRules) => in_array('confirmed', $nameRules[1]));

        if (! $confirmedParamNameRules) {
            return $rules;
        }

        foreach ($confirmedParamNameRules as $confirmedParamNameRule) {
            $rules->offsetSet(
                "$confirmedParamNameRule[0]_confirmation",
                array_filter($confirmedParamNameRule[1], fn ($rule) => $rule !== 'confirmed'),
            );
        }

        return $rules;
    }
}
