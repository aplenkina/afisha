<?php

namespace App\Catalog\Infrastructure\Repository;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class CategoryRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();

        if (null === $argumentType || !is_subclass_of($argumentType, CategoryRequestInterface::class)) {
            return [];
        }
        //        dd($argumentType);
        // get the value from the request, based on the argument name
        $value = $request->attributes->get($argument->getName());

        if (!is_string($value)) {
            return [];
        }

        //        $object = $this->serializer->deserialize(
        //            $this->normalizeJsonRequestData($request, $argumentType),
        //            $argumentType,
        //            JsonEncoder::FORMAT,
        //            [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
        //        );

        // create and return the value object
        return [$argumentType::fromString($value)];
    }
}
