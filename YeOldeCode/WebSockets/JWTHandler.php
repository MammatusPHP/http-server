<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

use Chimera\Mapping\Routing\FetchEndpoint;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Mammatus\Http\Server\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WyriHaximus\React\Http\Middleware\Session;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;
use WyriHaximus\React\Stream\Json\JsonStreamFactory;

use function assert;
use function base64_encode;
use function bin2hex;
use function getenv;
use function hash;
use function mt_rand;
use function random_bytes;
use function time;
use function WyriHaximus\getIn;

/**
 * @FetchEndpoint(path="/thruway/jwt/{realm:[a-zA-Z0-9\-\_]{1,}}.json", query=FetchJWT::class)
 */
final class JWTHandler
{
    /** @var Realm[] */
    private array $realms = [];

    public function __construct(array $realms)
    {
        $this->realms = $realms;
    }

    /**
     * @throws UnknownRealmException
     */
    public function token(ServerRequestInterface $request): ResponseInterface
    {
        $realm = $request->getQueryParams()['realm'];
        if (! isset($this->realms[$realm])) {
            throw UnknownRealmException::create($realm);
        }

        if ($this->realms[$realm]->getAuth()->isEnabled() === false) {
            throw UnknownRealmException::create($realm);
        }

        if ($this->realms[$realm]->getAuth()->isEnabled() === true && $this->realms[$realm]->getAuth()->getKey() === '') {
            throw UnknownRealmException::create($realm);
        }

        $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);
        assert($session instanceof Session);

        $realmSalt   = getenv('THRUWAY_REALM_SALT');
        $authKeySalt = getenv('THRUWAY_AUTH_KEY_SALT');
        $hashedRealm = hash('sha512', $realmSalt . $realm . $realmSalt);
        $hashedRealm = base64_encode($hashedRealm);
        $token       = (new Builder())
            ->setIssuer($hashedRealm)
            ->setAudience($hashedRealm)
            ->setId(bin2hex(random_bytes(mt_rand(256, 512))), true)
            ->setIssuedAt(time())
            ->setNotBefore(time() - 13)
            ->setExpiration(time() + 13)
            ->set('authid', $session === null ? 0 : getIn($session->getContents(), 'user.id', 0))
            ->sign(new Sha256(), $authKeySalt . $this->realms[$realm]->getAuth()->getKey() . $authKeySalt)
            ->getToken();

        return JsonResponse::create(
            200,
            [],
            JsonStreamFactory::createFromArray(['token' => $token])
        );
    }
}
