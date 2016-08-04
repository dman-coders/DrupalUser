<?php

namespace Drupal\du;

use Goutte\Client;
use Psr\Log\LoggerInterface;

/**
 * Drupal User Agent, for interacting with Drupal style websites.
 *
 * \Drupal\du\DrupalUser.
 */
class DrupalUser extends Client {

  private $site = array(
    'url' => 'https://www.drupal.org',
    'username' => '',
    'password' => '',
  );

  private $authenticated = NULL;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
    $this->site = (array)$site_def + $this->site;

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

    $this->log("Contacting server...", array('!url' => $this->site['url']));

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

    if (!empty($this->site['username'])) {
      // It's worth trying to log in.
      $this->login();
      if (isset($this->authenticated)) {
        $info['Authenticated'] = $this->authenticated;
      }
    }

    return $this->site + $info;
  }

  public function login() {
    $this->log("Authenticating...", array());
    $login_url = $this->site['url'] . '/user';
    $crawler = $this->request('GET', $login_url);
    $form = $crawler->selectButton('Log in')->form();
    // Beware TFA here now!

    $crawler = $this->submit($form, array('name' => $this->site['username'], 'pass' => $this->site['password']));

    // See if that looked like a success
    $this->authenticated = 'dunno';
    return $this;
  }


  /**
   * Log a message.
   *
   * Using a Symfony Logger interface if one was provided,
   * or just the drush_log if not.
   *
   * @param $message
   * @param $strings
   * @param $level
   */
  private function log($message, $strings, $logLevel = 'notice') {
    if (is_callable('xdebug_call_function')) {
      $function = xdebug_call_function();
    }
    else {
      $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
    }

    if ($this->logger) {
      /* \Psr\Log\LoggerInterface $logger */
      $this->logger->log($logLevel, '[' . $function . '] ' . dt($message, $strings));
    }
    else {
      drush_log('[' . $function . '] ' . dt($message, $strings), $logLevel);
    }
  }

}
