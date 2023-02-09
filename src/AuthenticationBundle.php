<?php

namespace Almefy\AuthenticationBundle;

use Almefy\AuthenticationBundle\DepdendencyInjection\AuthenticationExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AuthenticationBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new AuthenticationExtension();
        }

        return $this->extension;
    }
}
