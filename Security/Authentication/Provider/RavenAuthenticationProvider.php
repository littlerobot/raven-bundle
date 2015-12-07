<?php

/*
 * This file is part of the MisdRavenBundle for Symfony2.
 *
 * (c) University of Cambridge
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Misd\RavenBundle\Security\Authentication\Provider;

use Misd\RavenBundle\Exception\LoginTimedOutException;
use Misd\RavenBundle\Exception\OpenSslException;
use Misd\RavenBundle\Exception\RavenException;
use Misd\RavenBundle\Security\Authentication\Token\RavenUserToken;
use Misd\RavenBundle\Service\RavenServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Raven authentication provider.
 *
 * @author Chris Wilkinson <chris.wilkinson@admin.cam.ac.uk>
 */
class RavenAuthenticationProvider implements AuthenticationProviderInterface
{
    /**
     * User provider.
     *
     * @var UserProviderInterface
     */
    private $userProvider;

    /**
     * Raven service.
     *
     * @var RavenServiceInterface
     */
    private $raven;

    /**
     * Container.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Logger, or null.
     *
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param UserProviderInterface $userProvider User provider.
     * @param RavenServiceInterface $raven        Raven service.
     * @param ContainerInterface    $container    Service container.
     * @param LoggerInterface|null  $logger       Logger.
     */
    public function __construct(
        UserProviderInterface $userProvider,
        RavenServiceInterface $raven,
        ContainerInterface $container,
        LoggerInterface $logger = null
    )
    {
        $this->userProvider = $userProvider;
        $this->raven = $raven;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (null !== $this->logger) {
            $this->logger->debug('Testing WLS response');
        }

        if ((time() - $token->getAttribute('issue')->getTimestamp() > 30)) {
            throw new LoginTimedOutException();
        } elseif (false === $this->validateToken($token)) {
            throw new RavenException('Invalid Raven response');
        } elseif ($token->getAttribute('kid') !== $this->raven->getKid()) {
            throw new RavenException('Invalid Raven kid');
        } elseif (rawurldecode($token->getAttribute('url')) !== $this->container->get('request')->getUri()) {
            throw new RavenException('URL mismatch');
        } elseif ('pwd' !== $token->getAttribute('auth') && null !== $token->getAttribute('auth')) {
            throw new RavenException('Invalid Raven auth');
        } elseif ('pwd' !== $token->getAttribute('sso') && null === $token->getAttribute('auth')) {
            throw new RavenException('Invalid Raven sso');
        }

        if (null !== $this->logger) {
            $this->logger->debug('WLS response tests passed');
        }

        $user = $this->userProvider->loadUserByUsername($token->getUsername());

        return new RavenUserToken($user, $user->getRoles());
    }

    /**
     * Validate a Raven user token.
     *
     * @param TokenInterface $token Raven user token.
     *
     * @return bool true if the token is valid, false otherwise.
     *
     * @throws OpenSslException If there is an OpenSSL problem.
     */
    protected function validateToken(TokenInterface $token)
    {
        // @codeCoverageIgnoreStart
        if (false === function_exists('openssl_verify')) {
            throw new OpenSslException('OpenSSL is unavailable');
        }
        // @codeCoverageIgnoreEnd

        $data = implode(
            '!',
            array(
                $token->getAttribute('ver'),
                $token->getAttribute('status'),
                $token->getAttribute('msg'),
                $token->getAttribute('issue')->format('Ymd\THis\Z'),
                $token->getAttribute('id'),
                $token->getAttribute('url'),
                $token->getUsername(),
                $token->getAttribute('auth'),
                $token->getAttribute('sso'),
                $token->getAttribute('life'),
                $token->getAttribute('params'),
            )
        );

        $sig = base64_decode(
            preg_replace(
                array(
                    '/-/',
                    '/\./',
                    '/_/',
                ),
                array(
                    '+',
                    '/',
                    '=',
                ),
                rawurldecode($token->getAttribute('sig'))
            )
        );

        $key = openssl_pkey_get_public($this->raven->getCertificate());

        $result = openssl_verify($data, $sig, $key);

        openssl_free_key($key);

        switch ($result) {
            case 1:
                return true;
                break;
            case 0:
                return false;
                break;
            // @codeCoverageIgnoreStart
            default:
                throw new OpenSslException('OpenSSL has returned a error when verifying the signature');
                break;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof RavenUserToken;
    }
}
