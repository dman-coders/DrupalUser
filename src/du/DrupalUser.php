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

    parent::__construct();
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
    $info = array();
    /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
    $crawler = $this->request('GET', $this->site['url']);
    $info['HTML Title'] = $crawler->filter('title')->text();

    /*
    $html_title = $crawler->filter('title')->text();
    if ($html_title->count() > 0) {
      $info['HTML Title'] = $html_title->each(function ($node) {
        return trim($node->text());
      });
      $info['HTML Title'] = $html_title->text();
    }
    */

    // Dig out some info from the response.
    /** @var \Symfony\Component\BrowserKit\Response $response */
    $response = $this->getResponse();
    if (!empty($response->getHeader('Server'))) {
      $info['Server'] = $response->getHeader('Server');
    }

    return $this->site + $info;
  }

  public function login() {
    $login_url = $this->site['url'] . '/user';
    $crawler = $this->request('GET', $login_url);
    $form = $crawler->selectButton('Log in')->form();
    // Beware TFA here now!
    $crawler = $this->submit($form, array('name' => $user, 'pass' => $pass));

    return $this->site;
  }

}
