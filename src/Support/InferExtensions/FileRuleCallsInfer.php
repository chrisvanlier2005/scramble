<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\Rules\File;

class FileRuleCallsInfer implements MethodReturnTypeExtension, StaticMethodReturnTypeExtension
{
    /**
     * Determine whether this extension should handle the given type.
     *
     * @param \Dedoc\Scramble\Support\Type\ObjectType|string $type
     * @return bool
     */
    public function shouldHandle(ObjectType|string $type): bool
    {
        if ($type instanceof ObjectType && $type->name === File::class) {
            return true;
        }

        if ($type === File::class) {
            return true;
        }

        return false;
    }

    /**
     * Get the return type of the method call.
     *
     * @param \Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent $event
     * @return \Dedoc\Scramble\Support\Type\Type|null
     */
    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return $event->getInstance();
    }

    /**
     * Get the return type of a static method call.
     *
     * @param \Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent $event
     * @return \Dedoc\Scramble\Support\Type\Type|null
     */
    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'types', 'default', 'image' => new Generic(File::class),
            default => null,
        };
    }
}
