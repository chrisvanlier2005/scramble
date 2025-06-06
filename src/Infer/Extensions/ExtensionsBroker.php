<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\Rules\RuleExtension;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Generator;

class ExtensionsBroker
{
    /** @var PropertyTypeExtension[] */
    private array $propertyTypeExtensions;

    /** @var MethodReturnTypeExtension[] */
    private array $methodReturnTypeExtensions;

    /** @var AnyMethodReturnTypeExtension[] */
    private array $anyMethodReturnTypeExtensions;

    /** @var MethodCallExceptionsExtension[] */
    private array $methodCallExceptionsExtensions;

    /** @var StaticMethodReturnTypeExtension[] */
    private array $staticMethodReturnTypeExtensions;

    /** @var FunctionReturnTypeExtension[] */
    private array $functionReturnTypeExtensions;

    /** @var AfterClassDefinitionCreatedExtension[] */
    private array $afterClassDefinitionCreatedExtensions;

    /** @var AfterSideEffectCallAnalyzed[] */
    private array $afterSideEffectCallAnalyzedExtensions;

    /** @var array<class-string<RuleExtension>> */
    private array $validationRuleExtensions;

    /**
     * @var string<InferExtension>[]
     */
    private array $priorities = [];

    public function __construct(public readonly array $extensions = [])
    {
        $this->buildExtensions();
    }

    /**
     * @param  string<InferExtension>[]  $extensions
     */
    public function priority(array $priority)
    {
        $this->priorities = array_merge($this->priorities, $priority);

        $this->buildExtensions();

        return $this;
    }

    private function buildExtensions()
    {
        $extensions = $this->sortExtensionsInOrder($this->extensions, $this->priorities);

        $this->propertyTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof PropertyTypeExtension;
        });

        $this->methodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof MethodReturnTypeExtension;
        });

        $this->anyMethodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof AnyMethodReturnTypeExtension;
        });

        $this->methodCallExceptionsExtensions = array_filter($extensions, function ($e) {
            return $e instanceof MethodCallExceptionsExtension;
        });

        $this->staticMethodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof StaticMethodReturnTypeExtension;
        });

        $this->functionReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof FunctionReturnTypeExtension;
        });

        $this->afterClassDefinitionCreatedExtensions = array_filter($extensions, function ($e) {
            return $e instanceof AfterClassDefinitionCreatedExtension;
        });

        $this->afterSideEffectCallAnalyzedExtensions = array_filter($extensions, function ($e) {
            return $e instanceof AfterSideEffectCallAnalyzed;
        });

        $this->validationRuleExtensions = array_filter($extensions, function ($e) {
            return is_string($e) && is_a($e, RuleExtension::class, true);
        });
    }

    private function sortExtensionsInOrder(array $arrayToSort, array $arrayToSortWithItems): array
    {
        // 1) Figure out which items match any of the given “order” patterns
        $isMatched = []; // parallel boolean array
        $matchedItems = []; // will collect items for sorting
        foreach ($arrayToSort as $item) {
            $found = false;
            foreach ($arrayToSortWithItems as $pattern) {
                if ($item::class === $pattern) {
                    $found = true;
                    break;
                }
            }
            $isMatched[] = $found;
            if ($found) {
                $matchedItems[] = $item;
            }
        }

        // 2) Sort the matched-items list by the order of patterns
        usort($matchedItems, function ($a, $b) use ($arrayToSortWithItems) {
            $rank = array_flip($arrayToSortWithItems);
            // Find the first pattern each item matches
            $getRank = function ($item) use ($rank) {
                foreach ($rank as $pattern => $idx) {
                    if ($item::class === $pattern) {
                        return $idx;
                    }
                }

                return PHP_INT_MAX; // fallback (should not happen)
            };

            return $getRank($a) <=> $getRank($b);
        });

        // 3) Rebuild the final array
        $result = [];
        $mIndex = 0;
        foreach ($arrayToSort as $i => $item) {
            if ($isMatched[$i]) {
                // pull from the sorted‐matches list
                $result[] = $matchedItems[$mIndex++];
            } else {
                // untouched item
                $result[] = $item;
            }
        }

        return $result;
    }

    public function getPropertyType($event)
    {
        foreach ($this->propertyTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getInstance())) {
                continue;
            }

            if ($propertyType = $extension->getPropertyType($event)) {
                return $propertyType;
            }
        }

        return null;
    }

    public function getMethodReturnType($event)
    {
        foreach ($this->methodReturnTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getInstance())) {
                continue;
            }

            if ($returnType = $extension->getMethodReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }

    public function getMethodCallExceptions($event)
    {
        $exceptions = [];

        foreach ($this->methodCallExceptionsExtensions as $extension) {
            if (! $extension->shouldHandle($event->getInstance())) {
                continue;
            }

            if ($extensionExceptions = $extension->getMethodCallExceptions($event)) {
                $exceptions = array_merge($exceptions, $extensionExceptions);
            }
        }

        return $exceptions;
    }

    public function getStaticMethodReturnType($event)
    {
        foreach ($this->staticMethodReturnTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getCallee())) {
                continue;
            }

            if ($returnType = $extension->getStaticMethodReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }

    public function getFunctionReturnType($event)
    {
        foreach ($this->functionReturnTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getName())) {
                continue;
            }

            if ($returnType = $extension->getFunctionReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }

    public function afterClassDefinitionCreated($event)
    {
        foreach ($this->afterClassDefinitionCreatedExtensions as $extension) {
            if (! $extension->shouldHandle($event->name)) {
                continue;
            }

            $extension->afterClassDefinitionCreated($event);
        }
    }

    public function afterSideEffectCallAnalyzed(SideEffectCallEvent $event)
    {
        foreach ($this->afterSideEffectCallAnalyzedExtensions as $extension) {
            if (! $extension->shouldHandle($event)) {
                continue;
            }

            $extension->afterSideEffectCallAnalyzed($event);
        }
    }

    public function getValidationRuleType(
        Type $rule,
        Generator\Types\Type $previousType,
        TypeTransformer $openApiTransformer,
    ): ?Generator\Types\Type {
        foreach ($this->validationRuleExtensions as $extension) {
            $instance = new $extension($openApiTransformer);

            if (!$instance->shouldHandle($rule)) {
                continue;
            }

            if ($propertyType = $instance->handle($previousType, $rule)) {
                return $propertyType;
            }
        }

        return null;
    }

    public function getAnyMethodReturnType(AnyMethodCallEvent $event)
    {
        foreach ($this->anyMethodReturnTypeExtensions as $extension) {
            if ($returnType = $extension->getMethodReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }
}
