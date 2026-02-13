<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain\Normalizers;

use BigGive\Identity\Domain\Person;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class HasPasswordNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    /**
     * @psalm-suppress PossiblyUnusedMethod - called by PHP-DI
     */
    public function __construct(private readonly PropertyNormalizer $normalizer)
    {
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod - called by Symfony Serializer.
     */
    public function getSupportedTypes(): array
    {
        return [Person::class => true,];
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        \assert(is_array($data));
        // in other usages normalize can return several types, as we've been using it is always array.

        $data['has_password'] = $object->getPasswordHash() !== null;

        // Don't leak unnecessary sensitive password data back in the response object.
        unset($data['password'], $data['raw_password']);

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Person;
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->normalizer->setSerializer($serializer);
    }
}
