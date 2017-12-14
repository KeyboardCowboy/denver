<?php
/**
 * @file
 * Contains \DenverTest.
 */

use \PHPUnit\Framework\TestCase;

require_once __DIR__ . '/MockDenver.php';

/**
 * Unit tests for the Denver plugin.
 */
class DenverTest extends TestCase {
  /**
   * A denver object for testing.
   *
   * @var \MockDenver
   */
  private $denver;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->denver = new MockDenver();
  }

  /**
   * Test that single command syntax works in all forms.
   */
  public function testFormatCommandSingles() {
    $file = (object) [
      'name' => 'command-singles',
      'filename' => __DIR__ . '/envs/command-singles.env.drushrc.yml',
    ];

    $this->denver->loadEnvFile($file);
    foreach ($this->denver->getEnvironments()['command-singles']['commands'] as $command => $info) {
      $this->assertInternalType('integer', $command);
      $command_string = $this->denver->formatCommand($info);
      $this->assertEquals("drush @test -y {$info['name']} arg1val arg2val --opt1 --opt2=0 --opt3=string --opt4=opt4a,opt4b,opt4c", $command_string, "Failed to parse command {$info['name']} properly.");
    }
  }

  /**
   * Test that duplicate command syntax works in all forms.
   */
  public function testFormatCommandDupes() {
    $command_strings = [];
    $file = (object) [
      'name' => 'command-dupes',
      'filename' => __DIR__ . '/envs/command-dupes.env.drushrc.yml',
    ];

    $this->denver->loadEnvFile($file);
    foreach ($this->denver->getEnvironments()['command-dupes']['commands'] as $command => $info) {
      $this->assertInternalType('integer', $command);
      $command_strings[] = $this->denver->formatCommand($info);
    }

    $this->assertEquals("drush @self comm-1 arg1val arg2val --opt1 --opt2=0 --opt3=string --opt4=opt4a,opt4b,opt4c", $command_strings[0], 'Failed to parse long-form dupe syntax correctly.');
    $this->assertEquals("drush @self comm-2 arg1val arg2val --opt1 --opt2=0 --opt3=string --opt4=opt4a,opt4b,opt4c", $command_strings[1], 'Failed to parse med-form dupe syntax correctly.');
    $this->assertEquals("drush @self -y comm-1 arg1val", $command_strings[2], 'Failed to parse med-form dupe syntax correctly.');
  }

}
