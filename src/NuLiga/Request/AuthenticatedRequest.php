<?php
/** @noinspection PhpUndefinedClassInspection */
declare(strict_types=1);

namespace ContaoBayern\NuligadataBundle\NuLiga\Request;

use Contao\CoreBundle\Monolog\ContaoContext;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Cache\InvalidArgumentException;
use Monolog\Logger;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
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

    /**
     * @var string
     */
    protected $lastStatusMessage;


    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var FilesystemAdapter
     */
    protected $appCache;

    public function __construct(ContainerInterface $container, AdapterInterface $cache, Logger $logger)
    {
        $this->container = $container;
        $this->appCache = $cache;
        $this->logger = $logger;

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
            foreach ([
                         self::NU_ACCESS_TOKEN_KEY,
                         self::NU_REFRESH_TOKEN_KEY,
                         self::NU_TOKEN_TIMESTAMP_KEY,
                     ] as $key) {
                $cacheItem = $this->appCache->getItem($key);
                if ($cacheItem->isHit()) {
                    $this->tokens[$key] = $cacheItem->get();
                    // $this->logger->addDebug("hole gecachten Wert '$key': ".$this->tokens[$key],
                    //     [ 'contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
                    // );
                }
            }
        } catch (InvalidArgumentException $e) {
            $this->logger->addError(
                'versuche gecachte keys zu bestimmen; ignoriere: '.$e->getMessage(),
                [ 'contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR) ]
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
            $message = "konnte credentials nicht bestimmen (müssen in parameters.yml gesetzt sein)";
            $this->logger->addError($message, [ 'contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR) ]);
            throw new RuntimeException($message);
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
            // $this->logger->addDebug("token sollte noch gültig sein: keine erneute authentifizierung",
            //     [ 'contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
            // );

            return true;
        }
        $this->lastStatus = 0;
        $this->lastStatusMessage = '';

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
        $this->lastStatusMessage = $response->getReasonPhrase();

        if ($response->getStatusCode() === 200) {
            $this->cacheTokenValues($response->getBody()->getContents());
            return true;
        }

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
        $this->lastStatusMessage = $response->getReasonPhrase();

        if ($response->getStatusCode() === 200) {
            $this->cacheTokenValues($response->getBody()->getContents());
        } else {
            $message = "authentifizierung nicht erfolgreich. statuscode: ".$response->getStatusCode();
            $this->logger->addError($message, [ 'contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
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
            $this->logger->log(
                LogLevel::ERROR,
                'Konnte response nicht als JSON parsen',
                [ 'contao' => new ContaoContext(__METHOD__) ]
            );
            return;
        }

        $this->tokens[self::NU_ACCESS_TOKEN_KEY] = $tokens['access_token'];
        $this->tokens[self::NU_REFRESH_TOKEN_KEY] = $tokens['refresh_token'];
        $this->tokens[self::NU_TOKEN_TIMESTAMP_KEY] = time();

        try {
            foreach ([
                         self::NU_ACCESS_TOKEN_KEY,
                         self::NU_REFRESH_TOKEN_KEY,
                         self::NU_TOKEN_TIMESTAMP_KEY,
                     ] as $key) {
                $cacheItem = $this->appCache->getItem($key);
                $cacheItem->set($this->tokens[$key]);
                $this->appCache->save($cacheItem);
            }
        } catch (InvalidArgumentException $e) {
            $this->logger->addError($e->getMessage(), [ 'contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @param string $url
     * @return array
     */
    public function authenticatedRequest(string $url): array
    {
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
        $this->lastStatusMessage = $response->getReasonPhrase();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            return [];
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

    /**
     * @return string
     */
    public function getLastStatusMessage(): string
    {
        return $this->lastStatusMessage;
    }

}
