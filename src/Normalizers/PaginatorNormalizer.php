<?php

namespace Apie\PaginationPlugin\Normalizers;

use Pagerfanta\Pagerfanta;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class PaginatorNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    public function normalize($object, $format = null, array $context = [])
    {
        /** @var Pagerfanta $object */
        return $this->serializer->normalize($object->getCurrentPageResults(), $format, $context);
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Pagerfanta;
    }
}
