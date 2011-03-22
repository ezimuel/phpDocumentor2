<?php
/**
 * DocBlox
 *
 * @category   DocBlox
 * @package    Base
 * @copyright  Copyright (c) 2010-2011 Mike van Riel / Naenius. (http://www.naenius.com)
 */

/**
 * Base class used for all classes which need to support logging and core functionality.
 *
 * This class also contains the (leading) current version number.
 *
 * @category   DocBlox
 * @package    Base
 * @author     Mike van Riel <mike.vanriel@naenius.com>
 */
abstract class DocBlox_Abstract
{
  /**
   * The actual version number of DocBlox.
   *
   * @var int
   */
  const VERSION = '0.8.10';

  /**
   * The logger used to capture all messages send by the log method.
   *
   * @see DocBlox_Abstract::log()
   *
   * @var DocBlox_Log
   */
  static protected $logger       = null;

  /**
   * The logger used to capture all messages send by the log method and send them to stdout.
   *
   * @see DocBlox_Abstract::log()
   *
   * @var DocBlox_Log
   */
  static protected $stdout_logger       = null;

  /**
   * The logger used to capture the debug messages send by the debug method.
   *
   * @see DocBlox_Abstract::debug()
   * @var DocBlox_Log
   */
  static protected $debug_logger = null;

  /**
   * The config containing overrides for the defaults.
   *
   * @see DocBlox_Abstract::getConfig()
   *
   * @var DocBlox_Config
   */
  static protected $config       = null;

  /**
   * The current level of logging,
   *
   * This variable is used by i.e. the verbosity flag to enable more or less logging.
   *
   * @var string
   */
  static protected $log_level    = null;

  /**
   * Associative array containing all timers by name.
   *
   * @var float[]
   */
  protected $timer = array();

  /**
   * Initializes the default timer.
   *
   * @return void
   */
  public function __construct()
  {
    $this->resetTimer();
  }

  /**
   * Resets a timer (with the given name) to the current time.
   *
   * @param  string $name
   * @return void
   */
  protected function resetTimer($name = 'default')
  {
    $this->timer[$name] = microtime(true);
  }

  /**
   * Returns the time that has elapsed since the last reset of the timer.
   *
   * @param  string $name
   * @return mixed
   */
  protected function getElapsedTime($name = 'default')
  {
    return microtime(true) - $this->timer[$name];
  }

  /**
   * Returns the level of messages to log.
   *
   * If no level is set it tries to get the level from the config file.
   * @see    Zend_Log
   * @return void
   */
  public function getLogLevel()
  {
    if (self::$log_level === null)
    {
      $this->setLogLevel($this->getConfig()->logging->level);
    }

    return self::$log_level;
  }

  /**
   * Sets a new level to log messages of.
   *
   * @param  string $level Must be one of the Zend_Log LOG_* constants
   * @see    Zend_Log
   * @return void
   */
  public function setLogLevel($level)
  {
    if (!is_numeric($level))
    {
      if (!defined('DocBlox_Log::' . strtoupper($level)))
      {
        throw new InvalidArgumentException('Expected one of the constants of the DocBlox_Log class, "'
          . $level . '" received');
      }
      $level = constant('DocBlox_Log::'.strtoupper($level));
    }

    if (self::$logger)
    {
      self::$logger->setThreshold($level);
    }
    if (self::$stdout_logger)
    {
      self::$stdout_logger->setThreshold($level);
    }
    if (self::$debug_logger)
    {
      self::$debug_logger->setThreshold($level);
    }
    self::$log_level = $level;
  }

  /**
   * Logs a debug message post-fixed with timer information.
   *
   * The string which is sent to the debug logger looks like:
   *
   *     $message in {time} seconds.
   *
   * Thus for readability is advised to write messages as the following examples:
   *
   * * 'Processed parameters' (in 4 seconds)
   * * 'Written to log' (in 4 seconds)
   *
   * @param  string $message
   * @param  string $name
   * @return void
   */
  protected function debugTimer($message, $name = 'default')
  {
    $this->debug($message.' in '.number_format($this->getElapsedTime($name), 4).' seconds');
    $this->resetTimer($name);
  }

  /**
   * Logs the given to a debug log.
   *
   * This method only works if the Log Level is higher than DEBUG.
   * If anything other than a string is passed than the item is var_dumped and then stored.
   * If there is no debug logger object than this method will instantiate it.
   *
   * @see    DocBlock_Abstract::setLogLevel()
   * @see    Zend_Log
   * @param  string|array|object $message
   * @return void
   */
  protected function debug($message)
  {
    if (!self::$debug_logger)
    {
      $config = $this->getConfig();
      self::$debug_logger = new DocBlox_Log($config->logging->paths->errors);
    }

    self::$debug_logger->log($message, DocBlox_Log::DEBUG);
  }

  /**
   * Logs the message to the log with the given priority.
   *
   * This method only works if the Log Level is higher than the given priority.
   * If there is no logger object than this method will instantiate it.
   * In contrary to the debug statement this only logs strings.
   *
   * @see    DocBlock_Abstract::setLogLevel()
   * @see    Zend_Log
   * @param  string $message
   * @return void
   */
  public function log($message, $priority = DocBlox_Log::INFO)
  {
    if ($priority == DocBlox_Log::DEBUG)
    {
      $this->debug($message);
      return;
    }

    if (!self::$logger || !self::$stdout_logger)
    {
      $config = $this->getConfig();

      // log to file
      self::$logger = new DocBlox_Log($config->logging->paths->default);
      self::$logger->setThreshold($this->getLogLevel());

      // log to stdout
      self::$stdout_logger = new DocBlox_Log(DocBlox_Log::FILE_STDOUT);
      self::$stdout_logger->setThreshold($this->getLogLevel());
    }

    self::$logger->log($message, $priority);
    self::$stdout_logger->log($message, $priority);
  }

  /**
   * Returns the configuration for DocBlox.
   *
   * @return DocBlox_Config
   */
  public function getConfig()
  {
    return self::config();
  }

  /**
   * Returns the configuration for DocBlox.
   *
   * @return DocBlox_Config
   */
  public static function config()
  {
    if (self::$config === null)
    {
      self::$config = new DocBlox_Config(dirname(__FILE__) . '/../../data/docblox.tpl.xml');
    }

    return self::$config;
  }
}