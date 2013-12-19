<?php
/**
 * This file is part of the ImboClient package
 *
 * (c) Beherca <beherca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was
 * distributed with this source code.
 */

namespace ImboClient;

use ImboClient\Driver\DriverInterface,
    ImboClient\Driver\cURL as DefaultDriver,
    ImboClient\Url\Files\FileInterface,
    ImboClient\Url\Files\File,
    ImboClient\Url\Files\QueryInterface,
    ImboClient\Exception\InvalidArgumentException,
    ImboClient\Exception\ServerException,
    DateTime,
    DateTimeZone;

/**
 * Client that interacts with Imbo servers
 *
 * This client includes methods that can be used to easily interact with Imbo servers.
 *
 * @package Client
 * @author Beherca <beherca@gmail.com>
 */
class FileClient implements ClientInterface {
    /**
     * The server URLs
     *
     * @var array
     */
    private $serverUrls;

    /**
     * Driver used by the client
     *
     * @var DriverInterface
     */
    private $driver;

    /**
     * Public key used for signed requests
     *
     * @var string
     */
    private $publicKey;

    /**
     * Private key used for signed requests
     *
     * @var string
     */
    private $privateKey;

    /**
     * Class constructor
     *
     * @param array|string $serverUrls One or more URLs to the Imbo server, including protocol
     * @param string $publicKey The public key to use
     * @param string $privateKey The private key to use
     * @param DriverInterface $driver Optional driver to set
     * @param Version $version A version instance
     */
    public function __construct($serverUrls, $publicKey, $privateKey, DriverInterface $driver = null, Version $version = null) {
        $this->serverUrls = $this->parseUrls($serverUrls);
        $this->publicKey  = $publicKey;
        $this->privateKey = $privateKey;

        if ($driver === null) {
            $driver = new DefaultDriver();
        }

        if ($version === null) {
            $version = new Version();
        }

        $driver->setRequestHeaders(array(
            'Accept' => 'application/json,file/*',
            'User-Agent' => $version->getVersionString(),
        ));

        $this->setDriver($driver);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerUrls() {
        return $this->serverUrls;
    }

    /**
     * {@inheritdoc}
     */
    public function setDriver(DriverInterface $driver) {
        $this->driver = $driver;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusUrl() {
        return new Url\Status($this->serverUrls[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserUrl() {
        return new Url\User($this->serverUrls[0], $this->publicKey, $this->privateKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilesUrl() {
        return new Url\Files($this->serverUrls[0], $this->publicKey, $this->privateKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getFileUrl($fileIdentifier) {
        $hostname = $this->getHostForFileIdentifier($fileIdentifier);

        return new Url\File($hostname, $this->publicKey, $this->privateKey, $fileIdentifier);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataUrl($fileIdentifier) {
        $hostname = $this->getHostForFileIdentifier($fileIdentifier);

        return new Url\Metadata($hostname, $this->publicKey, $this->privateKey, $fileIdentifier);
    }

    /**
     * {@inheritdoc}
     */
    public function addFile($path) {
        $fileIdentifier = $this->getFileIdentifier($path);
        $fileUrl = $this->getFileUrl($fileIdentifier)->getUrl();

        $url = $this->getSignedUrl(DriverInterface::PUT, $fileUrl);

        return $this->driver->put($url, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function addFileFromString($file) {
        if (empty($file)) {
            throw new InvalidArgumentException('Specified file is empty');
        }

        $fileIdentifier = $this->getFileIdentifierFromString($file);
        $fileUrl = $this->getFileUrl($fileIdentifier)->getUrl();

        $url = $this->getSignedUrl(DriverInterface::PUT, $fileUrl);

        return $this->driver->putData($url, $file);
    }

    /**
     * {@inheritdoc}
     */
    public function addFileFromUrl($url) {
        if ($url instanceof Url\FileInterface) {
            $url = $url->getUrl();
        }

        $response = $this->driver->get($url);

        return $this->addFileFromString($response->getBody());
    }

    /**
     * {@inheritdoc}
     */
    public function fileExists($path) {
        $fileIdentifier = $this->getFileIdentifier($path);

        return $this->fileIdentifierExists($fileIdentifier);
    }

    /**
     * {@inheritdoc}
     */
    public function fileIdentifierExists($fileIdentifier) {
        try {
            $response = $this->headFile($fileIdentifier);
        } catch (ServerException $e) {
            if ($e->getCode() === 404) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function headFile($fileIdentifier) {
        $url = $this->getFileUrl($fileIdentifier)->getUrl();

        return $this->driver->head($url);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile($fileIdentifier) {
        $fileUrl = $this->getFileUrl($fileIdentifier)->getUrl();
        $url = $this->getSignedUrl(DriverInterface::DELETE, $fileUrl);

        return $this->driver->delete($url);
    }

    /**
     * {@inheritdoc}
     */
    public function editMetadata($fileIdentifier, array $metadata) {
        $metadataUrl = $this->getMetadataUrl($fileIdentifier)->getUrl();
        $url = $this->getSignedUrl(DriverInterface::POST, $metadataUrl);

        $data = json_encode($metadata);

        return $this->driver->post($url, $data, array(
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($data),
            'Content-MD5' => md5($data),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function replaceMetadata($fileIdentifier, array $metadata) {
        $metadataUrl = $this->getMetadataUrl($fileIdentifier)->getUrl();
        $url = $this->getSignedUrl(DriverInterface::PUT, $metadataUrl);

        $data = json_encode($metadata);

        return $this->driver->putData($url, $data, array(
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($data),
            'Content-MD5' => md5($data),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMetadata($fileIdentifier) {
        $metadataUrl = $this->getMetadataUrl($fileIdentifier)->getUrl();
        $url = $this->getSignedUrl(DriverInterface::DELETE, $metadataUrl);

        return $this->driver->delete($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($fileIdentifier) {
        $url = $this->getMetadataUrl($fileIdentifier)->getUrl();
        $response = $this->driver->get($url);

        $body = json_decode($response->getBody(), true);

        return $body;
    }

    /**
     * {@inheritdoc}
     */
    public function getNumFiles() {
        $url = $this->getUserUrl()->getUrl();
        $response = $this->driver->get($url);

        $body = json_decode($response->getBody());

        return (int) $body->numFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles(QueryInterface $query = null) {
        $params = array();

        if ($query) {
            // Retrieve query parameters, reduce array down to non-empty values
            $params = array_filter(array(
                'page'      => $query->page(),
                'limit'     => $query->limit(),
                'metadata'  => $query->returnMetadata(),
                'from'      => $query->from(),
                'to'        => $query->to(),
                'query'     => $query->metadataQuery(),
            ), function($item) {
                return !empty($item);
            });

            // JSON-encode metadata query, if present
            if (isset($params['query'])) {
                $params['query'] = json_encode($params['query']);
            }
        }

        $url = $this->getFilesUrl();

        // Add query params
        foreach ($params as $key => $value) {
            $url->addQueryParam($key, $value);
        }

        // Fetch the response
        $response = $this->driver->get($url->getUrl());

        $files = json_decode($response->getBody(), true);
        $instances = array();

        foreach ($files as $file) {
            $instances[] = new File($file);
        }

        return $instances;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileData($fileIdentifier) {
        $url = $this->getFileUrl($fileIdentifier);

        return $this->getFileDataFromUrl($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getFileDataFromUrl(Url\FileInterface $url) {
        return $this->driver->get($url->getUrl())->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function getFileProperties($fileIdentifier) {
        $response = $this->headFile($fileIdentifier);
        $headers = $response->getHeaders();

        return array(
            'width'     => (int) $headers->get('x-imbo-originalwidth'),
            'height'    => (int) $headers->get('x-imbo-originalheight'),
            'filesize'  => (int) $headers->get('x-imbo-originalfilesize'),
            'mimetype'  => $headers->get('x-imbo-originalmimetype'),
            'extension' => $headers->get('x-imbo-originalextension')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFileIdentifier($path) {
        $this->validateLocalFile($path);

        return $this->generateFileIdentifier(
            file_get_contents($path)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFileIdentifierFromString($file) {
        return $this->generateFileIdentifier($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerStatus() {
        $url = $this->getStatusUrl()->getUrl();

        try {
            $response = $this->driver->get($url);
        } catch (ServerException $e) {
            if ($e->getCode() === 500) {
                $response = $e->getResponse();
            } else {
                // re-throw same exception
                throw $e;
            }
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo() {
        $url = $this->getUserUrl()->getUrl();

        $response = $this->driver->get($url);
        $data = json_decode($response->getBody(), true);
        $data['lastModified'] = new DateTime($data['lastModified'], new DateTimeZone('UTC'));

        return $data;
    }

    /**
     * Generate an file identifier based on the data of the file
     *
     * @param string $data The actual file data
     * @return string
     */
    private function generateFileIdentifier($data) {
        return md5($data);
    }

    /**
     * Generate a signature that can be sent to the server
     *
     * @param string $method HTTP method (PUT, POST or DELETE)
     * @param string $url The URL to send a request to
     * @param string $timestamp UTC timestamp
     * @return string
     */
    private function generateSignature($method, $url, $timestamp) {
        $data = $method . '|' . $url . '|' . $this->publicKey . '|' . $timestamp;

        // Generate signature
        $signature = hash_hmac('sha256', $data, $this->privateKey);

        return $signature;
    }

    /**
     * Get a signed URL
     *
     * @param string $method HTTP method
     * @param string $url The URL to send a request to
     * @return string Returns a string with the necessary parts for authenticating
     */
    private function getSignedUrl($method, $url) {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature = $this->generateSignature($method, $url, $timestamp);

        $url = sprintf(
            '%s%ssignature=%s&timestamp=%s',
            $url,
            (strpos($url, '?') === false ? '?' : '&'),
            rawurlencode($signature),
            rawurlencode($timestamp)
        );

        return $url;
    }

    /**
     * Helper method to make sure a local file exists, and that it is not empty
     *
     * @param string $path The path to a local file
     * @throws InvalidArgumentException
     */
    private function validateLocalFile($path) {
        if (!is_file($path)) {
            throw new InvalidArgumentException('File does not exist: ' . $path);
        }

        if (!filesize($path)) {
            throw new InvalidArgumentException('File is of zero length: ' . $path);
        }
    }

    /**
     * Get a predictable hostname for the given file identifier
     *
     * @param string $fileIdentifier The file identifier
     * @return string
     */
    private function getHostForFileIdentifier($fileIdentifier) {
        $dec = hexdec($fileIdentifier[0] . $fileIdentifier[1]);

        return $this->serverUrls[$dec % count($this->serverUrls)];
    }

    /**
     * Parse server host URLs and prepare them for usage
     *
     * @param array|string $urls One or more URLs to an Imbo server
     * @return array Returns an array of URLs
     */
    private function parseUrls($urls) {
        $urls = (array) $urls;
        $result = array();
        $counter = 0;

        foreach ($urls as $serverUrl) {
            if (!preg_match('|^https?://|', $serverUrl)) {
                $serverUrl = 'http://' . $serverUrl;
            }

            $parts = parse_url($serverUrl);

            // Remove the port from the server URL if it's equal to 80 when scheme is http, or if
            // it's equal to 443 when the scheme is https
            if (
                isset($parts['port']) && (
                    ($parts['scheme'] === 'http' && $parts['port'] == 80) ||
                    ($parts['scheme'] === 'https' && $parts['port'] == 443)
                )
            ) {
                if (empty($parts['path'])) {
                    $parts['path'] = '';
                }

                $serverUrl = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
            }

            $serverUrl = rtrim($serverUrl, '/');

            if (!isset($result[$serverUrl])) {
                $result[$serverUrl] = $counter++;
            }
        }

        return array_flip($result);
    }
}
