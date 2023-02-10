<?php

namespace Almefy\AuthenticationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route(path: '/authenticate', name: 'almefy_authentication_authenticate')]
    public function authenticateAlmefyUser(): Response
    {
        return $this->json([]);
    }
}
