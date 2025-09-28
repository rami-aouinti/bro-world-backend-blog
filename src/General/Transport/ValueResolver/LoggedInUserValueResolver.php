<?php

declare(strict_types=1);

namespace App\General\Transport\ValueResolver;

use App\General\Infrastructure\Service\LexikJwtAuthenticatorService;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Generator;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Throwable;

/**
 * Example how to use this within your controller;
 *
 *  #[Route(path: 'some-path')]
 *  #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
 *  public function someMethod(\App\User\User\Domain\Entity\User $loggedInUser): Response
 *  {
 *      ...
 *  }
 *
 * This will automatically convert your security user to actual User entity that
 * you can use within your controller as you like.
 *
 * @package App\General\Transport\ValueResolver
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class LoggedInUserValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly LexikJwtAuthenticatorService $lexikJwtAuthenticatorService,
    ) {
    }

    /**
     * @throws MissingTokenException
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        $output = false;

        if ($argument->getName() === 'symfonyUser' && $argument->getType() === SymfonyUser::class) {
            $user = $this->lexikJwtAuthenticatorService->getUserId();

            if ($user === null && $argument->isNullable() === false) {
                throw new MissingTokenException('JWT Token not found');
            }

            $output = true;
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     *
     * @return Generator<SymfonyUser|null>
     *
     * @throws Throwable
     */
    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        yield $this->lexikJwtAuthenticatorService->getSymfonyUser();
    }
}
