# Almefy Authentication Bundle

Symfony Authentication Bundle integrating Almefy using Almefy PHP Client.

## Quick Guide

Below is a quick guide how to integrate Almefy Authentication Bundle in your Symfony project to test it easily. We are already
preparing a comprehensive documentation covering all possible use cases and parameters.

### Installation
##### Prerequisites

Bundle comes with several required configuration options that are exposed using environment values.

```ini
# Example .env Data
# Almefy Entity credentials and api url
ALMEFY_KEY=...
ALMEFY_SECRET=...
ALMEFY_API=...

# Local route to redirect and local route to authenticate
LOGIN_REDIRECT_URL=/sessions
LOGIN_AUTHENTICATE_URL=/almefy/authenticate

```

Configure routes
```yaml
# config/routes.yaml
almefy_authenticator:
    resource: '@AuthenticationBundle/config/routes.yaml'
    prefix: /almefy
```

Update `security.yaml`
```yaml
security:
    providers:
        almefy_user_identity_provider:
            id: Almefy\AuthenticationBundle\Security\AlmefyUserIdentityProvider
    firewalls:
        main:
            lazy: true
            provider: almefy_user_identity_provider
            entry_point: Almefy\AuthenticationBundle\Security\AuthenticationEntryPoint
            custom_authenticator:
                - Almefy\AuthenticationBundle\Security\AlmefyAuthenticator
            logout:
                path: app_logout
```

Update login page twig to add Almefy Widget
```html
{{ include('@!Authentication/almefy_widget.html.twig') }}
```

Login controller has to pass variables to twig template hosting almefy_widget:
```php
    public function __construct(private Client $client, private AlmefySessionManager $almefySessionManager)
    {
    }
# ...
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'almefyApi' => $this->client->getApi(), 
            'almefyKey' => $this->client->getKey()
        ]);
    }      
```

Alternatively you can bind them using services to expose them for twig automatically.

**Almefy Authentication Bundle** is available on Packagist as the [almefy/authentication-bundle](http://packagist.org/packages/almefy/authentication-bundle)
package. Run `composer require almefy/authentication-bundle` from the root of your project in terminal, and you are done. The minimum PHP version currently supported is 8.0.

### Client Usage

This bundle uses `almefy/client` as a core component to communicate with Almefy API. 
Make sure to check its documentation to get better understanding how everything works .

### License
The Almefy PHP SDK is licensed under the Apache License, version 2.0.
