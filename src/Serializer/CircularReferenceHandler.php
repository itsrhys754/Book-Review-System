<?php

namespace App\Serializer;

use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\JsonSerializationVisitor;

class CircularReferenceHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        return [
            [
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'type' => 'object',
                'method' => 'handleCircularReference',
            ],
        ];
    }

    public function handleCircularReference(JsonSerializationVisitor $visitor, $object, array $type)
    {
        return method_exists($object, 'getId') ? $object->getId() : spl_object_hash($object);
    }
}
