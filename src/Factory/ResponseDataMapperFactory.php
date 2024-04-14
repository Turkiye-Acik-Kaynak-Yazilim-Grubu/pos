<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Psr\Log\LoggerInterface;

/**
 * ResponseDataMapperFactory
 */
class ResponseDataMapperFactory
{
    /**
     * @param class-string               $gatewayClass
     * @param RequestDataMapperInterface $requestDataMapper
     * @param LoggerInterface            $logger
     *
     * @return ResponseDataMapperInterface
     */
    public static function createGatewayResponseMapper(string $gatewayClass, RequestDataMapperInterface $requestDataMapper, LoggerInterface $logger): ResponseDataMapperInterface
    {
        $classMappings = [
            ToslaPos::class        => ToslaPosResponseDataMapper::class,
            EstV3Pos::class        => EstPosResponseDataMapper::class,
            EstPos::class          => EstPosResponseDataMapper::class,
            GarantiPos::class      => GarantiPosResponseDataMapper::class,
            InterPos::class        => InterPosResponseDataMapper::class,
            KuveytPos::class       => KuveytPosResponseDataMapper::class,
            VakifKatilimPos::class => VakifKatilimPosResponseDataMapper::class,
            PayForPos::class       => PayForPosResponseDataMapper::class,
            PosNet::class          => PosNetResponseDataMapper::class,
            PosNetV1Pos::class     => PosNetV1PosResponseDataMapper::class,
            PayFlexV4Pos::class    => PayFlexV4PosResponseDataMapper::class,
            PayFlexCPV4Pos::class  => PayFlexCPV4PosResponseDataMapper::class,
        ];

        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass](
                $requestDataMapper->getCurrencyMappings(),
                $requestDataMapper->getTxTypeMappings(),
                $requestDataMapper->getSecureTypeMappings(),
                $logger
            );
        }

        throw new DomainException('unsupported gateway');
    }
}
