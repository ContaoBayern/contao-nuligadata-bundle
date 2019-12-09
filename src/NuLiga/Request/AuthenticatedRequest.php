<?php
/** @noinspection PhpUndefinedClassInspection */
declare(strict_types=1);

namespace ContaoBayern\NuligadataBundle\NuLiga\Request;

use Contao\CoreBundle\Monolog\ContaoContext;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Cache\InvalidArgumentException;
use \Monolog\Logger;
use Psr\Log\LogLevel;
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
     * @var int
     */
    protected $lastStatus;

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
            RequestOptions::HTTP_ERRORS => false, // false => disable throwing exceptions on an HTTP protocol errors
            RequestOptions::DEBUG       => false,
        ]);
    }

    /**
     * Ggf. vorhandenen gecachte Werte von access-tokens auslesen
     */
    protected function getCachedTokenValues()
    {
        try {
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
        } catch (InvalidArgumentException $e) {
            /** @var Logger $logger */
            $logger = $this->container->get('monolog.logger.contao');
            $logger->log(
                LogLevel::ERROR,
                'versuche gecachte keys zu bestimmen; ignoriere: '.$e->getMessage(),
                [ 'contao' => new ContaoContext(__METHOD__) ]
            );
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
            throw new RuntimeException("konnte credentials nicht bestimmen (müssen in parameters.yml gesetzt sein)");
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
     */
    public function authenticate(): bool
    {
        if ($this->lastStatus === 200 &&
            time() - $this->tokens[self::NU_TOKEN_TIMESTAMP_KEY] < 30 // 30 Sekunden
        ) {
            print "authentifiziere nicht neu\n";
            return true;
        }
        $this->lastStatus = 0;

        if (!empty($this->tokens[self::NU_REFRESH_TOKEN_KEY])) {
            if (!$this->renewAccessToken()) {
                $this->acquireAccessToken();
            }
        } else {
            $this->acquireAccessToken();
        }
        return $this->lastStatus === 200;
    }

    /**
     * Das access_token erneuern
     *
     * @returns bool
     */
    protected function renewAccessToken(): bool
    {
        if (!$this->hasCredentials()) {
            return false;
        }
        if (!$this->hasRefreshToken()) {
            return false;
        }

print "sende renew request\n"; // TODO nur debug

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

        $this->lastStatus = $response->getStatusCode();

        if ($response->getStatusCode() === 200) {
            $this->cacheTokenValues($response->getBody()->getContents());
            return true;
        }
printf("renew nicht erfolgreich. statuscode: %s\n", $response->getStatusCode()); // TODO nur debug
        return false;
    }

    /**
     * Anmelden (ein access_token besorgen)
     *
     * @throws RuntimeException
     */
    protected function acquireAccessToken(): void
    {
        if (!$this->hasCredentials()) {
            return;
        }

print "sende authentifizierungs request\n"; // TODO nur debug

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

        $this->lastStatus = $response->getStatusCode();

        if ($response->getStatusCode() === 200) {
            $this->cacheTokenValues($response->getBody()->getContents());
        } else {
            $message = "authentifizierung nicht erfolgreich. statuscode: ".$response->getStatusCode();
            throw new RuntimeException($message);
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
     */
    protected function cacheTokenValues(string $response): void
    {
        $tokens = json_decode($response, true);

        if (null === $tokens) {
            /** @var Logger $logger */
            $logger = $this->container->get('monolog.logger.contao');
            $logger->log(
                LogLevel::ERROR,
                'Konnte response nicht als JSON parsen',
                [ 'contao' => new ContaoContext(__METHOD__) ]
            );
            return;
        }

        $this->tokens[self::NU_ACCESS_TOKEN_KEY] = $tokens['access_token'];
        $this->tokens[self::NU_REFRESH_TOKEN_KEY] = $tokens['refresh_token'];
        $this->tokens[self::NU_TOKEN_TIMESTAMP_KEY] = time();

        /** @var $appCache FilesystemAdapter */
        $appCache = $this->container->get('cache.app');

        try {

            foreach ([
                         self::NU_ACCESS_TOKEN_KEY,
                         self::NU_REFRESH_TOKEN_KEY,
                         self::NU_TOKEN_TIMESTAMP_KEY,
                     ] as $key) {
                $cacheItem = $appCache->getItem($key);
                $cacheItem->set($this->tokens[$key]);
                $appCache->save($cacheItem);
            }
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @param string $url
     * @return array
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

        $this->lastStatus = $response->getStatusCode();

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

    /**
     * @return int
     */
    public function getLastStatus(): int
    {
        return $this->lastStatus;
    }
}
