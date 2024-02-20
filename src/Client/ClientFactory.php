<?php

namespace JustBetter\DynamicsClient\Client;

use Illuminate\Support\Facades\Cache;
use JustBetter\DynamicsClient\Exceptions\DynamicsException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use SaintSystems\OData\ODataClient;

/** @phpstan-consistent-constructor */
class ClientFactory
{
    public array $options = [];

    public string $url;

    public function __construct(public string $connection)
    {
        $config = config('dynamics.connections.'.$connection);

        if (! $config) {
            throw new DynamicsException(
                __('Connection ":connection" does not exist', ['connection' => $connection])
            );
        }

        if($config['auth'] === 'oauth2'){//When using the oauth connection we are not
            $this->url($config['base_url']);
        }else{
            $this->url($config['base_url'], $config['version'], "Company('{$config['company']}')");
        }
        $this
            ->options($config['options'])
            ->auth($config['username'], $config['password'], $config['auth'], $config['oauth2']??[])
            ->header('Accept', 'application/json')
            ->header('Content-Type', 'application/json');
    }

    public static function make(string $connection): static
    {
        return new static($connection);
    }

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function option(string $option, mixed $value): static
    {
        $this->options[$option] = $value;

        return $this;
    }

    public function headers(array $headers): static
    {
        $this->options['headers'] = $headers;

        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->options['headers'][$key] = $value;

        return $this;
    }

    public function etag(string $etag): static
    {
        $this->header('If-Match', $etag);

        return $this;
    }

    public function url(string ...$url): static
    {
        $this->url = implode('/', $url);

        return $this;
    }

    /**
     * @throws DynamicsException
     */
    public function auth(string $username, string $password, string $auth, array $oauthConfig = []): static
    {
        $credentials = [
            $username,
            $password,
        ];
        if ($auth === 'ntlm') {
            $credentials[] = 'ntlm';
        } elseif ($auth === 'oauth2') {
            $accessToken = $this->getOauth2Token($username, $password, $oauthConfig);
            $this->header('Authorization', 'Bearer ' . $accessToken);
            return $this;
        }
        $this->option('auth', $credentials);

        return $this;
    }

    public function getOauth2Token($username, $password, $oauthConfig): string
    {
        $currentToken = Cache::get('dynamicsOauth');
        $currentTime = time() + 180; // Set to 3 minutes in the future to prevent token expiration during request

        if (isset($currentToken->expires_on) && $currentTime <= $currentToken->expires_on) {
            return $currentToken->access_token;
        }

        $provider = new GenericProvider([
            'clientId'                => $oauthConfig['client_id'],
            'redirectUri'             => $oauthConfig['redirect_uri'],
            'urlAuthorize'            => "https://login.microsoftonline.com/{$oauthConfig['tenant_id']}/oauth2/authorize",
            'urlAccessToken'          => "https://login.microsoftonline.com/{$oauthConfig['tenant_id']}/oauth2/token",
            'urlResourceOwnerDetails' => "https://login.microsoftonline.com/{$oauthConfig['tenant_id']}/oauth2/resource",
        ]);

        try {
            // Try to get an access token using the resource owner password credentials grant.
            $accessToken = $provider->getAccessToken('password', [
                'username' => $username,
                'password' => $password,
                'resource' => $oauthConfig['resource'],
            ]);
        } catch (IdentityProviderException $e) {
            // Failed to get the access token
            throw new DynamicsException($e->getMessage());
        }

        // Store the new token in the cache
        Cache::put('dynamicsOauth', $accessToken, now()->addSeconds($accessToken->getExpires()));

        return $accessToken->getToken();
    }


    public function fabricate(): ODataClient
    {
        $httpProvider = new ClientHttpProvider();
        $httpProvider->setExtraOptions($this->options);

        return new ODataClient($this->url, null, $httpProvider);
    }
}
