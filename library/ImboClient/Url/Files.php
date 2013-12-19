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
 * User URL
 *
 * @package Urls\Files
 * @author Beherca <beherca@gmail.com>
 */
class Files extends Url implements UrlInterface {
  
    /**
     * {@inheritdoc}
     */
    protected function getResourceUrl() {
      return sprintf(
          '%s/users/%s/files.json',
          $this->baseUrl,
          $this->publicKey,
          $this->fileIdentifier
      );
    }
    
}
