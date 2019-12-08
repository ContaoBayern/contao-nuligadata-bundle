<?php
/** @noinspection PhpUndefinedClassInspection */
declare(strict_types=1);

namespace ContaoBayern\NuligadataBundle\NuLiga\Request;

use Contao\Date;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuthenticatedRequest
{
    // Authentifizierungs scope: vgl.:
    // https://www.bhv-online.de/filemanager/BHV/Daten/Service%20und%20Downloads/Nuliga/HTD_OAuth2_ZugriffaufnuPortalRS_200918_1144.pdf
    const NU_AUTH_SCOPE = 'nuPortalRS_club';

    // cache keys für die aktuellen oauth token
    const NU_ACCESS_TOKEN_KEY = 'nu_access_token';
    const NU_REFRESH_TOKEN_KEY = 'nu_refresh_token';
    const NU_TOKEN_TIMESTAMP_KEY = 'nu_token_timestamp';

    // keys in der parameters.yml
    const PARAM_NU_PORTAL_RS_HOST = 'nuPortalRSHost';
    const PARAM_NU_CLIENT_ID = 'nuClientID';
    const PARAM_NU_CLIENT_SECRET = 'nuClientSecret';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $tokens;

    /**
     * @var array
     */
    protected $credentials;

    /**
     * Authentification constructor.
     *
     * @param ContainerInterface $container
     * @throws InvalidArgumentException
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->tokens = [
            self::NU_ACCESS_TOKEN_KEY    => null,
            self::NU_REFRESH_TOKEN_KEY   => null,
            self::NU_TOKEN_TIMESTAMP_KEY => null,
        ];

        $this->credentials = [
            self::PARAM_NU_CLIENT_ID      => null,
            self::PARAM_NU_CLIENT_SECRET  => null,
            self::PARAM_NU_PORTAL_RS_HOST => null,
        ];

        $this->getCachedTokenValues();

        $this->getParameterSettings();

        $this->client = new Client([
            'base_uri'                  => $this->credentials[self::PARAM_NU_PORTAL_RS_HOST],
            RequestOptions::HTTP_ERRORS => false, // false => disable throwing exceptions on an HTTP protocol errors // TODO: erhaltenen HTTP-Status-Code abfragen und behandeln
            RequestOptions::DEBUG       => false,
        ]);
    }

    /**
     * Ggf. vorhandenen gecachte Werte von access-tokens auslesen
     *
     * @throws InvalidArgumentException
     */
    protected function getCachedTokenValues()
    {
        /** @var $appCache FilesystemAdapter */
        $appCache = $this->container->get('cache.app');

        foreach ([
                     self::NU_ACCESS_TOKEN_KEY,
                     self::NU_REFRESH_TOKEN_KEY,
                     self::NU_TOKEN_TIMESTAMP_KEY,
                 ] as $key) {
            $cacheItem = $appCache->getItem($key);
            if ($cacheItem->isHit()) {
                $this->tokens[$key] = $cacheItem->get();
            }
        }
        print "gecachte Token-Daten\n";
        print_r($this->tokens);

        if (null !== $this->tokens[self::NU_TOKEN_TIMESTAMP_KEY]) {
            print "Tokeninformation Zeitstempel: " . Date::parse('Y-m-d H:i:s', $this->tokens[self::NU_TOKEN_TIMESTAMP_KEY]) . "\n";
        }
    }

    /**
     * Parameter aus dem Container auslesen
     *
     * @throws RuntimeException
     */
    protected function getParameterSettings(): void
    {
        $this->credentials = [];
        foreach ([
                     self::PARAM_NU_CLIENT_ID,
                     self::PARAM_NU_CLIENT_SECRET,
                     self::PARAM_NU_PORTAL_RS_HOST,
                 ] as $key) {
            if ($this->container->hasParameter($key)) {
                $this->credentials[$key] = $this->container->getParameter($key);
            }
        }
        if (!$this->hasCredentials()) {
            throw new RuntimeException("konnte credentials nicht bestimmen (siehe Datei parameters.yml)");
        }
    }

    /**
     * @return bool
     */
    protected function hasCredentials(): bool
    {
        return !empty($this->credentials[self::PARAM_NU_CLIENT_ID])
            && !empty($this->credentials[self::PARAM_NU_CLIENT_SECRET]);
    }

    /**
     * @return bool
     * // throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function authenticate(): bool
    {
        // TODO? Wenn wir ein gecachtes Renew-Token haben, versuchen, dieses zu verlängern
        // TODO: Logik "Versuche neues Access Token zu besorgen" aus renewAccessToken() entfernen und hier durchführen
        try {
            if (!empty($this->tokens[self::NU_REFRESH_TOKEN_KEY])) {
                try {
                    $this->renewAccessToken();
                    // FIXME: mit der RequestOptions::HTTP_ERRORS == false Einstellung im $this->>client
                    // sollten wir hier nicht nur Exceptions, sondern auch den Response-Status 4xx abfragen
                } catch (GuzzleException $e) {
                    $this->acquireAccessToken();
                }
            } else {
                $this->acquireAccessToken();
            }
        } catch (GuzzleException $e) {
            print $e->getMessage() . "\n";
            return false;
        }
        return true;
    }

    /**
     * Das access_token erneuern
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    protected function renewAccessToken(): void
    {
        if (!$this->hasCredentials()) {
            return;
        }
        if (!$this->hasRefreshToken()) {
            return;
        }
        print "sende renew request\n";

        $response = $this->client->request('POST', '/rs/auth/token', [
            RequestOptions::FORM_PARAMS => [
                'grant_type'    => "refresh_token",
                'refresh_token' => $this->tokens[self::NU_REFRESH_TOKEN_KEY],
                'client_id'     => $this->credentials[self::PARAM_NU_CLIENT_ID],
                'client_secret' => $this->credentials[self::PARAM_NU_CLIENT_SECRET],
                'scope'         => self::NU_AUTH_SCOPE,
            ],
            RequestOptions::HEADERS     => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        if ($response->getStatusCode() === 200) {
            $this->cacheTokenValues($response->getBody()->getContents());
        } else {
            printf("renew nicht erfolgreich. statuscode: %s\n", $response->getStatusCode());
        }
    }

    /**
     * @return bool
     */
    protected function hasRefreshToken(): bool
    {
        return $status = !empty($this->tokens[self::NU_REFRESH_TOKEN_KEY]);
    }

    /**
     * Die Token aus der Anmeldung bei der NuLiga-API im Cache speichern
     *
     * @param string $response
     * @throws InvalidArgumentException
     */
    protected function cacheTokenValues(string $response): void
    {
        $tokens = json_decode($response, true);

        if (null === $tokens) {
            print("Konnte response nicht als JSON parsen!\n");
            return;
        }

        $this->tokens[self::NU_ACCESS_TOKEN_KEY] = $tokens['access_token'];
        $this->tokens[self::NU_REFRESH_TOKEN_KEY] = $tokens['refresh_token'];
        $this->tokens[self::NU_TOKEN_TIMESTAMP_KEY] = time();

        /** @var $appCache FilesystemAdapter */
        $appCache = $this->container->get('cache.app');

        foreach ([
                     self::NU_ACCESS_TOKEN_KEY,
                     self::NU_REFRESH_TOKEN_KEY,
                     self::NU_TOKEN_TIMESTAMP_KEY,
                 ] as $key) {
            $cacheItem = $appCache->getItem($key);
            $cacheItem->set($this->tokens[$key]);
            $appCache->save($cacheItem);
        }
    }

    /**
     * Anmelden (ein access_token besorgen)
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    protected function acquireAccessToken(): void
    {
        if (!$this->hasCredentials()) {
            return;
        }
        print "sende authentifizierungs request\n";

        $response = $this->client->request('POST', '/rs/auth/token', [
            RequestOptions::FORM_PARAMS => [
                'grant_type'    => "client_credentials",
                'client_id'     => $this->credentials[self::PARAM_NU_CLIENT_ID],
                'client_secret' => $this->credentials[self::PARAM_NU_CLIENT_SECRET],
                'scope'         => self::NU_AUTH_SCOPE,
            ],
            RequestOptions::HEADERS     => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        if ($response->getStatusCode() === 200) {
            $this->cacheTokenValues($response->getBody()->getContents());
        } else {
            printf("authentifizierung nicht erfolgreich. statuscode: %s\n", $response->getStatusCode());
        }
    }

    /**
     * @param string $url
     * @return array
     * @throws GuzzleException
     */
    public function authenticatedRequest(string $url): array
    {
        print "sende Datenanfrage\n";
        if (!$this->hasAccessToken()) {
            print "Habe kein Access Token\n";
            return ['error' => 'Habe kein Access Token'];
        }

        $response = $this->client->request('GET', $url,
            [
                'headers' => [
                    'Authorization' => "Bearer " . $this->tokens[self::NU_ACCESS_TOKEN_KEY],
                    'Content-Type'  => '*/*',
                    'Accept'        => 'application/json',
                ],
            ]
        );

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            return [
                'error'  => $response->getReasonPhrase(),
                'status' => $response->getStatusCode(),
            ];
        }
    }

    /**
     * @return bool
     */
    protected function hasAccessToken(): bool
    {
        return !empty($this->tokens[self::NU_ACCESS_TOKEN_KEY]);
    }

}
