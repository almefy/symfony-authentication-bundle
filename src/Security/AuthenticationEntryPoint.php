<?php

namespace Almefy\AuthenticationBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator, private string $loginRoute = 'app_login')
    {
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $request->getSession()->getFlashBag()->add('note', 'You have to login in order to access this page.');

        return new RedirectResponse($this->urlGenerator->generate($this->loginRoute));
    }
}
