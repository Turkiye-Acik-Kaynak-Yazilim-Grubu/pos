<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\InterPos;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use function explode;
use function preg_split;

class InterPosSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    public function encode(array $data, ?string $txType = null): array
    {
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        //genelde ;; delimiter kullanilmis, ama bazen arasinda ;;; boyle delimiter de var.
        $resultValues = preg_split('/(;;;|;;)/', $data);
        if (false === $resultValues) {
            throw new NotEncodableValueException();
        }

        $result = [];
        foreach ($resultValues as $val) {
            [$key, $value] = explode('=', $val);
            $result[$key] = $value;
        }

        return $result;
    }
}
