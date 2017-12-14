<?php
/**
 * @file
 * Contains \MockDenver.
 */

require_once __DIR__ . '/../Denver.class.php';

/**
 * Test double for the Denver plugin.
 */
class MockDenver extends Denver {
  protected $contexts = [];

  public function loadEnvFile($file) {
    parent::loadEnvFile($file);
  }

  function __destruct() {
    $no=0;
  }
}
