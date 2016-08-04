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
    $this->site = (array) $site_def + $this->site;

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

    // It's worth trying to log in to get more info.
    $this->login();
    if (isset($this->authenticated)) {
      if ($this->authenticated === TRUE) {
        $info['Authenticated'] = "TRUE";
      }
      if ($this->authenticated === FALSE) {
        $info['Authenticated'] = "FALSE";
      }
    }

    return $this->site + $info;
  }

  /**
   * Authenticate using the given username credentials, if any.
   */
  public function login() {
    if (empty($this->site['username'])) {
      $this->log('No username provided, not authenticating', array(), 'info');
      return;
    }
    $this->log("Authenticating...", array());
    $login_url = $this->site['url'] . '/user';
    /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
    $crawler = $this->request('GET', $login_url);
    $form = $crawler->selectButton('Log in')->form();
    // Beware TFA here now!
    $crawler = $this->submit($form, array('name' => $this->site['username'], 'pass' => $this->site['password']));

    // See if that looked like a success.
    // Our best clue is the <body class="logged-in"> parameter we see
    // in almost all Drupal themes.
    $check_logged_in = $crawler->filter('body.logged-in')->count();
    if ($check_logged_in) {
      $this->log("Account authentication succeeded", array(), 'success');
      $this->authenticated = TRUE;
    }
    elseif($crawler->filter('#tfa-form')->count() > 0) {
      $this->log("Authentication requires TFA. Input needed!", array(), 'warning');
      // This is just Drupal.org specific so far.
      drush_notify_send_audio('T F A Needed') ;
      $this->log($crawler->filter('#tfa-form')->text(), array());

      $tfa_code = drush_prompt(dt('Please enter your current TFA code.'));
      $form = $crawler->selectButton('Verify')->form();
      $crawler = $this->submit($form, array('code' => $tfa_code));

      // Verify success again.
      // TODO: Remove this repetition somehow.
      $check_logged_in = $crawler->filter('body.logged-in')->count();
      if ($check_logged_in) {
        $this->log("TFA Authentication succeeded", array(), 'success');
        $this->authenticated = TRUE;
      }
      else {
        $this->log("TFA Authentication failed", array(), 'error');
        if ($messages = $this->getMessages($crawler)) {
          $this->log($messages, array(), 'warning');
        }
        $this->authenticated = FALSE;
      }

    }
    else {
      $this->log("Authentication failed", array(), 'error');
      if ($messages = $this->getMessages($crawler)) {
        $this->log($messages, array(), 'warning');
      }
      $this->authenticated = FALSE;
    }

    return $this;
  }

  /**
   * Scrape the given crawler DOM for alert messages.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *
   * @return null|string
   */
  protected function getMessages($crawler) {
    // Making assumptions that the theme and DOM are Drupally again here.
    // Search for both ID (preferred) and class (fallback) elements
    // called 'messages'.
    $messages = $crawler->filter('#messages');
    if ($messages->count() == 0) {
      $messages = $crawler->filter('.messages');
    }
    if ($messages->count() > 0) {
      if (!empty($messages->text())) {
        return trim($messages->text());
      }
    }
    return NULL;
  }

  /**
   * Log a message.
   *
   * Using a Symfony Logger interface if one was provided,
   * or just the drush_log if not.
   *
   * @param string $message
   * @param array $strings
   * @param string $logLevel
   */
  private function log($message, $strings, $logLevel = 'notice') {
    $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
    if ($this->logger) {
      /* \Psr\Log\LoggerInterface $logger */
      $this->logger->log($logLevel, '[' . $function . '] ' . dt($message, $strings));
    }
    else {
      drush_log('[' . $function . '] ' . dt($message, $strings), $logLevel);
    }
  }

}
