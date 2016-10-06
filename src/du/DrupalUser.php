<?php

namespace Drupal\du;

use \Goutte\Client;
use \Symfony\Component\DomCrawler\Crawler;

/**
 * Drupal User Agent, for interacting with Drupal style websites.
 *
 * \Drupal\du\DrupalUser.
 */
class DrupalUser extends Client {

  private $site = array(
    'uri'      => 'https://www.drupal.org',
    'username' => '',
    'password' => '',
    'content_selector' => '#content',
  );

  private $authenticated = NULL;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
  protected $cookieJarFile;

  /**
   * A DrupalUser Client.
   *
   * @param array $site_def
   *   A keyed array of connection details for connecting to a site.
   *   Expected keys:
   *   - uri
   *   - username
   *   - password
   *   - content_selector
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
   * CRUD getter.
   *
   * @param string $attr
   *   Site attribute to fetch.
   *
   * @return string|array
   *   Individual value or
   *   Keyed array of site info.
   *   See __construct() for description.
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
   * Makes a connection and scrapes some basic diagnostics from the actual site.
   */
  public function getSiteInfo() {
    $info = array();

    $this->log("Contacting server :uri ...", array(':uri' => $this->site['uri']));

    /** @var Crawler $crawler */
    $crawler = $this->request('GET', $this->site['uri']);
    $info['HTML Title'] = $crawler->filter('title')->text();

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
      return $this;
    }
    $this->log("Authenticating...", array());
    $login_url = $this->site['uri'] . '/user';
    /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
    $crawler = $this->request('GET', $login_url);
    if ($this->isLoggedIn($crawler)) {
      $this->log('Already logged in. Use Logout if you need a fresh session', array(), 'info');
      return $this;
    }
    $form = $crawler->selectButton('Log in')->form();
    $parameters = array(
      'name' => $this->site['username'],
      'pass' => $this->site['password'],
    );
    $crawler = $this->submit($form, $parameters);

    // See if that looked like a success.
    if ($this->isLoggedIn($crawler)) {
      $this->log("Account authentication succeeded", array(), 'success');
      $this->authenticated = TRUE;
    }
    elseif ($crawler->filter('#tfa-form')->count() > 0) {
      $this->log("Authentication requires TFA. Input needed!", array(), 'warning');
      // This is just Drupal.org specific so far.
      // Make a big deal about interaction needed.
      drush_notify_send_audio('Two Factor Authentication Key Needed');
      $this->log($crawler->filter('#tfa-form')->text(), array());
      $tfa_code = drush_prompt(dt('Please enter your current TFA code.'));
      $form = $crawler->selectButton('Verify')->form();
      $crawler = $this->submit($form, array('code' => $tfa_code));

      // Verify success again.
      // TODO: Remove this repetition somehow.
      if ($this->isLoggedIn($crawler)) {
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

    if ($this->authenticated) {
      // This would be a great time save cookies.
      $this->writeCookieJarFile();
    }

    return $this;
  }

  /**
   * De-authenticate from the site.
   */
  public function logout() {
    $logout_url = $this->site['uri'] . '/user/logout';
    $this->request('GET', $logout_url);
    $this->writeCookieJarFile();
    $this->log("Logged out", array(), 'success');
  }

  /**
   * Retrieve the given page from the site.
   *
   * If possible, return just the title and content body as plain text.
   */
  public function getPage($path) {
    $url = $this->site['uri'] . $path;
    $this->log("Fetching URL :url", array(':url' => $url), 'debug');
    /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
    $crawler = $this->request('GET', $url);
    $content = new \Html2Text\Html2Text($crawler->filter($this->site['content_selector'])->html(), array('width' => 0));
    return array(
      'title' => $crawler->filter('title')->first()->text(),
      'h1' => $crawler->filter('h1')->first()->text(),
      'content' => $content->getText(),
    );
  }

  /**
   * Returns if the current session is authenticated.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   Current active DOM.
   *
   * @return bool
   *   Logged in or not.
   *
   * @ingroup Utility
   */
  protected function isLoggedIn(Crawler $crawler) {
    // Our best clue is the <body class="logged-in"> parameter we see
    // in almost all Drupal themes.
    return $crawler->filter('body.logged-in')->count() > 0;
  }

  /**
   * Scrape the given crawler DOM for alert messages.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The current page being scraped.
   *
   * @return null|string
   *   The contents of the message box.
   */
  protected function getMessages(Crawler $crawler) {
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
   *   Text.
   * @param array $strings
   *   Replacement strings.
   * @param string $logLevel
   *   One of error|warning|success|notice|info.
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

  /**
   * If someone tells us to use a cookie jar file, load it.
   *
   * @param string $cookieJarFile
   *   Temporary storage path for the file.
   *
   * @return $this
   *    For chaining.
   */
  public function readCookieJarFile($cookieJarFile) {
    if (empty($cookieJarFile)) {
      $cookieJarFile = $this->cookieJarFile;
    }
    $this->cookieJarFile = $cookieJarFile;
    if (!is_readable($cookieJarFile)) {
      $this->log('Cannot read cookie Jar File %cookieJarFile', array('%cookieJarFile' => $cookieJarFile), 'warning');
      return $this;
    }
    $cookie_data = unserialize(file_get_contents($cookieJarFile));
    $this->cookieJar = $cookie_data;
    return $this;
  }

  /**
   * Serializes the current client cookie state to a persistent file.
   *
   * @param string $cookieJarFile
   *   Temporary storage path for the file.
   *
   * @return $this
   *    For chaining.
   */
  public function writeCookieJarFile($cookieJarFile = NULL) {
    if (empty($cookieJarFile)) {
      $cookieJarFile = $this->cookieJarFile;
    }
    if (empty($this->cookieJarFile)) {
      $this->log('No Cookie Jar File defined.', array(), 'warning');
      return $this;
    }
    if (file_exists($cookieJarFile) && !is_writable($cookieJarFile)) {
      $this->log('Cannot write cookie Jar File %cookieJarFile', array('%cookieJarFile' => $cookieJarFile), 'warning');
      return $this;
    }

    $cookie_data = serialize($this->cookieJar);
    file_put_contents($cookieJarFile, $cookie_data);
    $this->log('Wrote cookie Jar File %cookieJarFile', array('%cookieJarFile' => $cookieJarFile), 'warning');
    return $this;
  }

}
