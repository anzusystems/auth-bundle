AnzuSystems Auth Bundle by Petit Press a.s. (www.sme.sk)
=====

Provides authorization functionality among Anzusystems' projects.

---

## Installation

From within container execute the following command to download the latest version of the bundle:
```console
$ composer require anzusystems/auth-bundle --no-scripts
```

### Step 3: Use the Bundle

Configure the `AnzuAuthBundle` in `config/anzu_systems_auth.yaml`:

```yaml
anzu_systems_auth:
  cookie:
    domain: .anzusystems.localhost
    secure: false # use true for PROD environment!
  jwt:
    audience: anz
    algorithm: ES256 # enum (ES256|RS256), default "ES256"
    public_cert: '%env(base64:AUTH_JWT_PUBLIC_CERT)%' # string representation of a public certificate
    private_cert: '%env(base64:AUTH_JWT_PRIVATE_CERT)%' # string representation of a private certificate
  authorization:
    enabled: true
    refresh_token:
      storage:
        redis:
          service_id: SharedTokenStorageRedis # service id of \Redis instance
    auth_redirect_default_url: http://admin-dam.anzusystems.localhost
    auth_redirect_query_url_allowed_pattern: '^https?://(.*)\.anzusystems\.localhost(:\d{2,5})$'
    type: json_credentials
```

Configure the [SecurityBundle](https://symfony.com/doc/current/reference/configuration/security.html) in `config/security.yaml`:

```yaml
security:
  providers:
    app_user_provider_email:
      entity:
        class: App\Entity\User
        property: email

  auth:
    pattern: ^/api/auth/
    stateless: true
    provider: app_user_provider_email
    json_login:
      check_path: auth_login
      success_handler: AnzuSystems\AuthBundle\Security\AuthenticationSuccessHandler
      failure_handler: AnzuSystems\AuthBundle\Security\AuthenticationFailureHandler
    logout:
      path: auth_logout

  access_control:
    - { path: ^/api/auth/, roles: PUBLIC_ACCESS }
```

Configure routing:
```php
$routes
    ->import('@AnzuSystemsAuthBundle/Controller/Api/JsonCredentialsAuthController.php', type: 'attribute')
    ->prefix('/api/auth/');
```
