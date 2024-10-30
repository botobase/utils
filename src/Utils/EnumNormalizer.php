<?php

namespace Yabx\Botobase\Utils;

use ArrayObject;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use UnitEnum;

class EnumNormalizer implements NormalizerInterface, DenormalizerInterface {

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool {
        return $data instanceof UnitEnum;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array|ArrayObject|bool|float|int|null|string {
        return $object->value;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool {
        return is_subclass_of($type, UnitEnum::class);
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed {
        return call_user_func([$type, 'from'], $data);
    }

    public function getSupportedTypes(?string $format): array {
        return [UnitEnum::class => true];
    }

}