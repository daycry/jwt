<?php

namespace Daycry\JWT;

use CodeIgniter\Config\BaseConfig;

use Lcobucci\JWT\Configuration;
//use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Signer\Key\InMemory;

use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;

class JWT
{
    /**
     * JWT Config
     */
    private $JWTConfig = null;

    /**
     * Configuration Class
     */
    private $configuration = null;

    /**
     * Split data if array
     */
    private $split = false;

    /**
     * Name of attribute of data
     */
    private $paramData = 'data';

    public function __construct(BaseConfig $config = null)
    {
        $this->JWTConfig = $config;

        if ($this->JWTConfig == null) {
            $this->JWTConfig = config('JWT');
        }

        $this->configuration = Configuration::forSymmetricSigner(
            new $this->JWTConfig->algorithm(),
            InMemory::base64Encoded($this->JWTConfig->signer)
        );
    }

    /**
     * Set the attibute to data claim
     * Used if data is not an array
     */
    public function setParamData(string $data) :JWT
    {
        $this->paramData = $data;

        return $this;
    }

    public function setSplitData(bool $value = true) :JWT
    {
        $this->split = $value;

        return $this;
    }

    public function encode($data, $uid = null) :string
    {
        $now   = new \DateTimeImmutable();

        $token = $this->configuration->builder();

        if (is_array($data) || is_object($data)) {
            if ($this->split) {
                foreach ($data as $key => $value) {
                    $token->withClaim($key, $value);
                }
            } else {
                $token->withClaim($this->paramData, \json_encode($data));
            }
        } else {
            $token->withClaim($this->paramData, $data);
        }

        $uid = ($uid !== null ) ? $uid : $this->JWTConfig->uid;

        // Configures a new claim, called "uid"
        if ($uid) {
            $token->withClaim('uid', $uid);
        }

        // Configures the issuer (iss claim)
        $token->issuedBy($this->JWTConfig->issuer)
            // Configures the audience (aud claim)
            ->permittedFor($this->JWTConfig->audience)
            // Configures the id (jti claim)
            ->identifiedBy($this->JWTConfig->identifier)
            // Configures the time that the token was issue (iat claim)
            ->issuedAt($now)
            // Configures the time that the token can be used (nbf claim)
            ->canOnlyBeUsedAfter($now->modify($this->JWTConfig->canOnlyBeUsedAfter))
            // Configures the expiration time of the token (exp claim)
            ->expiresAt($now->modify($this->JWTConfig->expiresAt))
            ->withHeader('type', 'Bearer');

        // Builds a new token;
        $token = $token->getToken($this->configuration->signer(), $this->configuration->signingKey());

        return $token->toString();
    }

    public function decode($data)
    {
        $token = $this->configuration->parser()->parse($data);

        $clock = new \Lcobucci\Clock\FrozenClock(new \DateTimeImmutable());

        try {

            $constraints = [
                new SignedWith($this->configuration->signer(), $this->configuration->signingKey()),
                new IssuedBy($this->JWTConfig->issuer),
                new ValidAt($clock),
                new IdentifiedBy($this->JWTConfig->identifier),
                new PermittedFor($this->JWTConfig->audience)
            ];

            $this->configuration->validator()->assert($token, ...$constraints);

        } catch (RequiredConstraintsViolated $e) {

            if( $this->JWTConfig->throwable )
            {
                throw $e;
            }else{
                return $e;
            }
        }

        return $token->claims();
    }
}
