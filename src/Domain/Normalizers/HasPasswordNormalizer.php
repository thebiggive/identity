<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain\Normalizers;

use BigGive\Identity\Domain\Person;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class HasPasswordNormalizer implements NormalizerInterface
{
    public function __construct(private readonly ObjectNormalizer $normalizer)
    {
    }

    public function normalize(mixed $object, string $format = null, array $context = []): bool
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        $data['has_password'] = $object->getPasswordHash() !== null;

        return $data;
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Person;
    }
}
