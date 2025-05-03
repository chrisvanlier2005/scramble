<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class StaticRulesToParameter
{
    const RULES_PRIORITY = [
        'bool', 'boolean', 'numeric', 'int', 'integer', 'file', 'image', 'string', 'array', 'exists',
    ];

    public function __construct(
        private string $name,
        private KeyedArrayType|LiteralStringType $rules,
        private ?PhpDocNode $docNode,
        private TypeTransformer $openApiTransformer,
        private ExtensionsBroker $extensionsBroker,
        private string $in = 'query',
    ) {}

    public function generate(): ?Parameter
    {
        if (count($this->docNode?->getTagsByName('@ignoreParam') ?? [])) {
            return null;
        }

        // Support literal strings e.g. 'name' => 'string|required'
        if ($this->rules instanceof LiteralStringType) {
            $this->rules = new KeyedArrayType(
                items: [
                    new ArrayItemType_(
                        key: 'rules',
                        value: $this->rules,
                    ),
                ],
            );
        }
        /** @var \Illuminate\Support\Collection<int, mixed> $rules */
        $rules = Collection::make($this->rules->items)
            ->map(fn (ArrayItemType_ $rules) => $rules->value)
            ->map(function (mixed $rule) {
                if ($rule instanceof LiteralStringType) {
                    return $rule->value;
                }

                return $rule;
            })
            ->sortByDesc($this->rulesSorter());

        /** @var OpenApiType $type */
        $type = $rules->reduce(function (OpenApiType $type, $rule) {
            if (is_string($rule)) {
                return $this->getTypeFromStringRule($type, $rule);
            }

            if (
                ($handled = $this->extensionsBroker->getValidationRule($rule, $type, $this->openApiTransformer)) !== null
            ) {
                return $handled;
            }

            return $this->getTypeFromObjectRule($type, $rule);
        }, new UnknownType);

        $description = $type->description;
        $type->setDescription('');

        $parameter = Parameter::make($this->name, $this->in)
            ->setSchema(Schema::fromType($type))
            ->required($rules->contains('required') && $rules->doesntContain('sometimes'))
            ->description($description);

        return $this->applyDocsInfo($parameter);
    }

    private function applyDocsInfo(Parameter $parameter)
    {
        if (! $this->docNode) {
            return $parameter;
        }

        $description = (string) Str::of($this->docNode->getAttribute('summary') ?: '')
            ->append(' '.($this->docNode->getAttribute('description') ?: ''))
            ->trim();

        if ($description) {
            $parameter->description($description);
        }

        if (count($varTags = $this->docNode->getVarTagValues())) {
            $varTag = $varTags[0];

            $parameter->setSchema(Schema::fromType(
                $this->openApiTransformer->transform(PhpDocTypeHelper::toType($varTag->type)),
            ));
        }

        if ($examples = ExamplesExtractor::make($this->docNode)->extract(preferString: $parameter->schema->type instanceof StringType)) {
            $parameter->example($examples[0]);
        }

        if ($default = ExamplesExtractor::make($this->docNode, '@default')->extract(preferString: $parameter->schema->type instanceof StringType)) {
            $parameter->schema->type->default($default[0]);
        }

        if ($format = array_values($this->docNode->getTagsByName('@format'))[0]->value->value ?? null) {
            $parameter->schema->type->format($format);
        }

        if ($this->docNode->getTagsByName('@query')) {
            $parameter->setAttribute('isInQuery', true);

            $parameter->in = 'query';
        }

        return $parameter;
    }

    private function rulesSorter()
    {
        return function ($v) {
            if (! is_string($v)) {
                return -1; // We want objects to be at the end due to type extension modifications.
            }

            $index = array_search($v, static::RULES_PRIORITY);

            return $index === false ? -1 : count(static::RULES_PRIORITY) - $index;
        };
    }

    private function getTypeFromStringRule(OpenApiType $type, string $rule)
    {
        $rulesHandler = new RulesMapper($this->openApiTransformer);

        $explodedRule = explode(':', $rule, 2);

        $ruleName = $explodedRule[0];
        $params = isset($explodedRule[1]) ? explode(',', $explodedRule[1]) : [];

        return method_exists($rulesHandler, $ruleName)
            ? $rulesHandler->$ruleName($type, $params)
            : $type;
    }

    private function getTypeFromObjectRule(OpenApiType $type, $rule)
    {
        $rulesHandler = new RulesMapper($this->openApiTransformer);

        $methodName = Str::camel(class_basename(get_class($rule)));

        return method_exists($rulesHandler, $methodName)
            ? $rulesHandler->$methodName($type, $rule)
            : $type;
    }
}
