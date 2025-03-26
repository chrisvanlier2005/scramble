<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Throwable;

class FormRequestRuleExtractor
{
    /**
     * Extract the form rules from hte given function type.
     *
     * @param \Dedoc\Scramble\Support\Type\FunctionType $type
     * @param \Dedoc\Scramble\Support\RouteInfo $routeInfo
     * @param string $requestClassName
     * @return array<mixed, mixed>
     */
    public function extract(FunctionType $type, RouteInfo $routeInfo, string $requestClassName): KeyedArrayType
    {
        $rules = [];

        $returnType = $type->getReturnType();

        dd($returnType);

        return $returnType;
    }
}
