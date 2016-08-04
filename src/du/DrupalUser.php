<?php

namespace Drupal\du;

use Goutte\Client;


/**
 * Drupal User Agent, for interacting with Drupal style websites.
 *
 * \Drupal\du\DrupalUser.
 */
class DrupalUser extends Client {

  private $site = array(
    'url' => 'https://www.drupal.org',
    'username' => 'AzureDiamond',
    'password' => 'hunter2',
  );

  /**
   * A DrupalUser Client.
   *
   * @param array $site_def
   *   A keyed array of connection details for connecting to a site.
   *   Expected keys:
   *   - url
   *   - username
   *   - password
   *   .
   */
  public function __construct($site_def = array()) {
    // TODO validation.
    $this->site = $site_def + $this->site;
  }

  /**
   * Fetch attributes about a site.
   *
   * @param string $attr
   *   Site attribute to fetch.
   *
   * @return string|array
   */
  public function getSite($attr = NULL) {
    if (!empty($attr) && isset($this->site[$attr])) {
      return $this->site[$attr];
    }
    return $this->site;
  }

  /**
   * Fetch info from the site.
   *
   * Makes a connection and scrapes some basic diagnostics.
   */
  public function getSiteInfo() {
    return $this->site;
  }

}
