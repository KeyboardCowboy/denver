<?php
/**
 * @file
 * Contains \DenverTest.
 */

use \PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Denver.class.php';

/**
 * Unit tests for the Denver plugin.
 */
class DenverTest extends TestCase {
  /**
   * A denver object for testing.
   *
   * @var \Denver
   */
  private $denver;

  public function setUp() {
    parent::setUp();

    $this->denver = new Denver();
  }

  public function testYamlLoad() {

  }

}
