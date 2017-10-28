<?php

namespace Hussainweb\DrupalComposerInit;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers DrupalInitCommand
 */
class DrupalInitCommandTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var string
     */
    protected $cwd;

    protected $helperSet;

    protected function setUp()
    {
        $this->cwd = getcwd();
        chdir('/tmp');
    }

    protected function tearDown()
    {
         chdir($this->cwd);
         gc_collect_cycles();
    }

    protected function getHelperSet()
    {
        if ($this->helperSet === null) {
            $this->helperSet = new HelperSet([
                new FormatterHelper(),
                new QuestionHelper(),
            ]);
        }
        return $this->helperSet;
    }

    public function testDefaultInit()
    {
        $command = $this->getMockBuilder(DrupalInitCommand::class)
            ->setMethods([
                'getCore',
                'getHelperSet',
            ])
            ->getMock();
        $command->method('getCore')->willReturn('drupal/core ^8.4');
        $command->method('getHelperSet')->willReturn($this->getHelperSet());

        $tester = new CommandTester($command);
        $tester->execute([
            '--name' => 'test/test',
            '--author' => 'Test User <testuser@example.com>',
            '--description' => 'Test Description',
            '--core' => 'drupal/core ^8.4',
            '--stability' => 'dev',
        ]);

        $json = json_decode(file_get_contents('./composer.json'), true);

        $this->assertSame('test/test', $json['name']);
        $this->assertCount(1, $json['authors']);
        $this->assertSame('Test User', $json['authors'][0]['name']);
        $this->assertSame('testuser@example.com', $json['authors'][0]['email']);
        $this->assertSame('Test Description', $json['description']);
        $this->assertSame('project', $json['type']);
        $this->assertSame('dev', $json['minimum-stability']);
        $this->assertSame('composer', $json['repositories'][0]['type']);
        $this->assertSame('https://packages.drupal.org/8', $json['repositories'][0]['url']);

        $this->assertSame('web', $json['extra']['drupal-composer-helper']['web-prefix']);

        // @todo: Remove this test once the workaround for drupal-finder is fixed.
        $this->assertSame('type:drupal-core', $json['extra']['installer-paths']['web/core'][0]);

        $this->assertArrayNotHasKey('preserve-paths', $json['extra']);

        $this->assertArraySubset([
            'hussainweb/drupal-composer-helper' => '^1.0',
            'drupal/core' => '^8.4',
        ], $json['require']);

        unlink('./composer.json');
    }

    public function testDrupal7Mode()
    {
        $command = $this->getMockBuilder(DrupalInitCommand::class)
            ->setMethods([
                'getCore',
                'getHelperSet',
            ])
            ->getMock();
        $command->method('getCore')->willReturn('drupal/drupal ~7.0');
        $command->method('getHelperSet')->willReturn($this->getHelperSet());

        $tester = new CommandTester($command);
        $tester->execute([
            '--name' => 'test/test',
            '--author' => 'Test User <testuser@example.com>',
            '--description' => 'Test Description',
            '--core' => 'drupal/drupal ~7.0',
            '--stability' => 'dev',
            '--drupal-7' => true,
        ]);

        $json = json_decode(file_get_contents('./composer.json'), true);

        $this->assertSame('test/test', $json['name']);
        $this->assertCount(1, $json['authors']);
        $this->assertSame('Test User', $json['authors'][0]['name']);
        $this->assertSame('testuser@example.com', $json['authors'][0]['email']);
        $this->assertSame('Test Description', $json['description']);
        $this->assertSame('project', $json['type']);
        $this->assertSame('dev', $json['minimum-stability']);
        $this->assertSame('composer', $json['repositories'][0]['type']);
        $this->assertSame('https://packages.drupal.org/7', $json['repositories'][0]['url']);

        $this->assertSame('web', $json['extra']['drupal-composer-helper']['web-prefix']);

        // @todo: Remove this test once the workaround for drupal-finder is fixed.
        $this->assertSame('type:drupal-core', $json['extra']['installer-paths']['web/'][0]);
        $this->assertArrayNotHasKey('web/core', $json['extra']['installer-paths']);

        $this->assertArrayHasKey('preserve-paths', $json['extra']);

        $this->assertArrayNotHasKey('drupal/core', $json['require']);
        $this->assertArraySubset([
            'drupal/drupal' => '~7.0',
        ], $json['require']);

        unlink('./composer.json');
    }
}
