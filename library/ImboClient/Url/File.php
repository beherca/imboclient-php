<?php
/**
 * This file is part of the ImboClient package
 *
 * (c) Beherca <beherca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was
 * distributed with this source code.
 */

namespace ImboClient\Url;

/**
 * File URL
 *
 * @package Urls\File
 * @author Beherca <beherca@gmail.com>
 */
class File extends Url implements UrlInterface {
    /**
     * File identifier
     *
     * @var string
     */
    private $fileIdentifier;
    
    /**
     * Class constructor
     *
     * {@inheritdoc}
     * @param string $fileIdentifier The file identifier to use in the URL
     */
    public function __construct($baseUrl, $publicKey, $privateKey, $fileIdentifier) {
      parent::__construct($baseUrl, $publicKey, $privateKey);
    
      $this->fileIdentifier = $fileIdentifier;
    }
    
    /**
     * {@inheritdoc}
     */
    public function reset() {
      parent::reset();
    
      $this->fileIdentifier = substr($this->fileIdentifier, 0, 32);
    
      return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getResourceUrl() {
      return sprintf(
          '%s/users/%s/files/%s',
          $this->baseUrl,
          $this->publicKey,
          $this->fileIdentifier
      );
    }
    
}
