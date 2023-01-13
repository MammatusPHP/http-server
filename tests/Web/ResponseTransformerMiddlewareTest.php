<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Web;

use Mammatus\Http\Server\Web\ResponseTransformerMiddleware;
use Mammatus\Vhost\Healthz\HealthResult;
use stdClass;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function Safe\json_encode;

final class ResponseTransformerMiddlewareTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function type(): void
    {
        $resultString = 'yay';
        $result       = new HealthResult($resultString);
        $response     = $result->response();
        $transformer  = new ResponseTransformerMiddleware();
        $command      = new stdClass();

        foreach ([$response, $result] as $item) {
            self::assertSame(json_encode(['result' => $resultString]), (string) $transformer->execute($command, static fn (): object => $item)->getBody());
        }
    }
}
