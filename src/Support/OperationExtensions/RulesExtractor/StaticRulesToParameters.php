<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class StaticRulesToParameters
{
    private bool $mergeDotNotatedKeys = true;

    public function __construct(
        private KeyedArrayType $rules,
        array $validationNodesResults,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {}

    public function mergeDotNotatedKeys(bool $value)
    {
        $this->mergeDotNotatedKeys = $value;

        return $this;
    }

    public function handle()
    {
        $broker = app(ExtensionsBroker::class);

        return Collection::make($this->rules->items)
            ->map(function (ArrayItemType_ $rules) use ($broker) {
                return (new StaticRulesToParameter(
                    name: $rules->key,
                    rules: $rules->value,
                    docNode: $rules->attributes()['docNode'] ?? null,
                    openApiTransformer: $this->openApiTransformer,
                    extensionsBroker: $broker,
                    in: $this->in,
                ))->generate();
            })
            ->filter()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->values()
            ->all();
    }

    /**
     * @todo This is copied from the RulesToParameters, should be checked and potentially refactored.
     *
     * @return \Illuminate\Support\Collection
     */
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
