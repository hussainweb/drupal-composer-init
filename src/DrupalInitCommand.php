<?php

namespace Hussainweb\DrupalComposerInit;

use Composer\Command\InitCommand;
use Composer\DependencyResolver\Pool;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalInitCommand extends InitCommand
{

    protected function configure()
    {
        // @codingStandardsIgnoreStart
        $this
            ->setName('drupal-init')
            ->setDescription('Creates a Drupal composer.json file in current directory.')
            ->setDefinition([
                new InputOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the package'),
                new InputOption('description', null, InputOption::VALUE_REQUIRED, 'Description of package'),
                new InputOption('author', null, InputOption::VALUE_REQUIRED, 'Author name of package'),
                // new InputOption('version', null, InputOption::VALUE_NONE, 'Version of package'),
                new InputOption('type', null, InputOption::VALUE_OPTIONAL, 'Type of package (e.g. library, project, metapackage, composer-plugin)', 'project'),
                new InputOption('homepage', null, InputOption::VALUE_REQUIRED, 'Homepage of package'),
                new InputOption('require', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Package to require with a version constraint, e.g. foo/bar:1.0.0 or foo/bar=1.0.0 or "foo/bar 1.0.0"'),
                new InputOption('require-dev', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Package to require for development with a version constraint, e.g. foo/bar:1.0.0 or foo/bar=1.0.0 or "foo/bar 1.0.0"'),
                new InputOption('core', 'c', InputOption::VALUE_REQUIRED, 'Drupal Core or distribution, e.g. drupal/core or acquia/lightning', 'drupal/core'),
                new InputOption('stability', 's', InputOption::VALUE_REQUIRED, 'Minimum stability (empty or one of: '.implode(', ', array_keys(BasePackage::$stabilities)).')'),
                new InputOption('license', 'l', InputOption::VALUE_REQUIRED, 'License of package'),
                new InputOption('repository', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Add custom repositories, either by URL or using JSON arrays'),
                new InputOption('web-dir', 'w', InputOption::VALUE_REQUIRED, 'Specify the docroot (defaults to web)', 'web'),
                new InputOption('drupal-7', null, InputOption::VALUE_NONE, 'Use Drupal 7 packagist instead of Drupal 8'),
//                new InputOption('extensions', 'm', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Extensions (such as modules or themes) to require with a version constraint, e.g. panels:^4.0'),
//                new InputOption('extensions-dev', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Extensions (such as modules or themes) to require for development with a version constraint, e.g. panels:^4.0'),
            ])
            ->setHelp(<<<EOT
The <info>drupal-init</info> command creates a composer.json file
usable for Drupal projects in the current directory.

<info>php composer.phar drupal-init</info>

EOT
            );
        // @codingStandardsIgnoreEnd
    }

    protected function isDrupal7(InputInterface $input)
    {
        return $input->getOption('drupal-7');
    }

    protected function getExtraRequires(InputInterface $input)
    {
        $requires = [
            'd7' => [
                'php >=5.2.5',
                'ext-gd *',
                'ext-xml *',
                'ext-json *',
                'ext-openssl *',
                'ext-curl *',
                'ext-pdo *',
                'ext-pdo_mysql *',
                'cweagans/composer-patches ^1.6.0',
                'hussainweb/drupal-composer-helper ^1.0-beta3',
                'drupal-composer/preserve-paths ^0.1',
                $input->getOption('core'),
                'drupal/composer_autoloader ^1.0',
                'drush/drush ~8.0',
            ],
            'd8' => [
                'cweagans/composer-patches ^1.6.0',
                'hussainweb/drupal-composer-helper ^1.0',
                $input->getOption('core'),
                'drupal/console ^1.0.2',
                'drush/drush ~8.0|^9.0.0-beta8',
            ],
        ];
        return $this->isDrupal7($input) ? $requires['d7'] : $requires['d8'];
    }

    protected function getExtraRequireDevs(InputInterface $input)
    {
        $require_dev = [
            'behat/mink ~1.7',
            'behat/mink-goutte-driver ~1.2',
            'jcalderonzumba/gastonjs ~1.0.2',
            'jcalderonzumba/mink-phantomjs-driver ~0.3.1',
            'mikey179/vfsstream ~1.2',
            'phpunit/phpunit >=4.8.28 <5',
            'symfony/css-selector ~2.8',
        ];
        return $this->isDrupal7($input) ? [] : $require_dev;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = $input->getOptions();

        $options['require'] = array_merge($this->getExtraRequires($input), $options['require']);

        $options['require-dev'] = array_merge($this->getExtraRequireDevs($input), $options['require-dev']);

        $input->setOption('require', $options['require']);
        $input->setOption('require-dev', $options['require-dev']);
        $input->setOption('type', 'project');

        $status = parent::execute($input, $output);

        if (!$status) {
            $web_prefix = $input->getOption('web-dir');

            // Write extra to composer.json file.
            $file = new JsonFile(Factory::getComposerFile());
            $options = $file->read();
            $options['extra'] = [
                'drupal-composer-helper' => [
                    'web-prefix' => $web_prefix,
                ],
                'enable-patching' => true,
                'installer-paths' => [
                    $web_prefix . '/core' => [
                        'type:drupal-core',
                    ],
                ],
            ];
            $options['prefer-stable'] = true;
            $options['conflict'] = [
                'drupal/drupal' => '*',
            ];
            $options['config'] = [
                'sort-packages' => true,
                'optimize-autoloader' => true,
                'apcu-autoloader' => true,
                'secure-http' => false,
                'discard-changes' => true,
            ];

            if ($this->isDrupal7($input)) {
                $extra = &$options['extra'];
                $options['conflict'] = [
                    'drupal/core' => '8.*',
                ];

                $extra['drupal-composer-helper']['set-d7-paths'] = true;
                $extra['preserve-paths'] = [
                    $web_prefix . '/sites/all/libraries',
                    $web_prefix . '/sites/all/modules/custom',
                    $web_prefix . '/sites/all/modules/features',
                    $web_prefix . '/sites/all/themes/custom',
                    $web_prefix . '/sites/all/translations',
                    $web_prefix . '/sites/default',
                ];
                $extra['installer-paths'][$web_prefix . '/'] = $extra['installer-paths'][$web_prefix . '/core'];
                unset($extra['installer-paths'][$web_prefix . '/core']);
            }

            $file->write($options);
        }

        return $status;
    }

    private function getRepositories(InputInterface $input)
    {
        $io = $this->getIO();
        // initialize repos if configured
        $config = Factory::createConfig($io);
        $repos = [new PlatformRepository()];

        $repositories = $input->getOption('repository');
        if ($repositories) {
            foreach ($repositories as $repo) {
                $repos[] = RepositoryFactory::fromString($io, $config, $repo);
            }
        }

        $packagist_url = 'https://packages.drupal.org/' . ($this->isDrupal7($input) ? '7' : '8');
        $repos[] = RepositoryFactory::createRepo($io, $config, [
            'type' => 'composer',
            'url' => $packagist_url,
        ]);

        // Add our Drupal packagist URL to the repositories so that it appears
        // in the composer.json file.
        $repositories[] = $packagist_url;
        $input->setOption('repository', $repositories);

        $repos[] = RepositoryFactory::createRepo($io, $config, [
            'type' => 'composer',
            'url' => 'https://packagist.org',
        ]);

        return new CompositeRepository($repos);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $git = $this->getGitConfig();
        $io = $this->getIO();
        /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
        $formatter = $this->getHelperSet()->get('formatter');

        $this->repos = $this->getRepositories($input);

        $io->writeError([
            '',
            $formatter->formatBlock('Welcome to the Drupal Composer config generator', 'bg=blue;fg=white', true),
            '',
        ]);

        // namespace
        $io->writeError([
            '',
            'This command will guide you through creating your composer.json config for a Drupal setup.',
            '',
        ]);

        $cwd = realpath(".");

        if (!$name = $input->getOption('name')) {
            $name = basename($cwd);
            $name = preg_replace('{(?:([a-z])([A-Z])|([A-Z])([A-Z][a-z]))}', '\\1\\3-\\2\\4', $name);
            $name = strtolower($name);
            if (isset($git['github.user'])) {
                $name = $git['github.user'] . '/' . $name;
            } elseif (!empty($_SERVER['USERNAME'])) {
                $name = $_SERVER['USERNAME'] . '/' . $name;
            } elseif (!empty($_SERVER['USER'])) {
                $name = $_SERVER['USER'] . '/' . $name;
            } elseif (get_current_user()) {
                $name = get_current_user() . '/' . $name;
            } else {
                // package names must be in the format foo/bar
                $name = $name . '/' . $name;
            }
            $name = strtolower($name);
        } else {
            if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}', $name)) {
                throw new \InvalidArgumentException(
                    'The package name '.$name.' is invalid, it should be lowercase and have a
                     vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                );
            }
        }

        $name = $io->askAndValidate(
            'Package name (<vendor>/<name>) [<comment>'.$name.'</comment>]: ',
            function ($value) use ($name) {
                if (null === $value) {
                    return $name;
                }

                if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}', $value)) {
                    throw new \InvalidArgumentException(
                        'The package name '.$value.' is invalid, it should be lowercase and have a
                         vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                    );
                }

                return $value;
            },
            null,
            $name
        );
        $input->setOption('name', $name);

        $description = $input->getOption('description') ?: false;
        $description = $io->ask(
            'Description [<comment>'.$description.'</comment>]: ',
            $description
        );
        $input->setOption('description', $description);

        if (null === $author = $input->getOption('author')) {
            if (isset($git['user.name']) && isset($git['user.email'])) {
                $author = sprintf('%s <%s>', $git['user.name'], $git['user.email']);
            }
        }

        $self = $this;
        $author = $io->askAndValidate(
            'Author [<comment>'.$author.'</comment>, n to skip]: ',
            function ($value) use ($self, $author) {
                if ($value === 'n' || $value === 'no') {
                    return;
                }
                $value = $value ?: $author;
                $author = $self->parseAuthorString($value);

                return sprintf('%s <%s>', $author['name'], $author['email']);
            },
            null,
            $author
        );
        $input->setOption('author', $author);

        $minimumStability = $input->getOption('stability') ?: 'dev';
        $minimumStability = $io->askAndValidate(
            'Minimum Stability [<comment>'.$minimumStability.'</comment>]: ',
            function ($value) use ($self, $minimumStability) {
                if (null === $value) {
                    return $minimumStability;
                }

                if (!isset(BasePackage::$stabilities[$value])) {
                    throw new \InvalidArgumentException(
                        'Invalid minimum stability "'.$value.'". Must be empty or one of: '.
                        implode(', ', array_keys(BasePackage::$stabilities))
                    );
                }

                return $value;
            },
            null,
            $minimumStability
        );
        $input->setOption('stability', $minimumStability);

        $license = $input->getOption('license') ?: false;
        $license = $io->ask(
            'License [<comment>'.$license.'</comment>]: ',
            $license
        );
        $input->setOption('license', $license);

        $web_dir = $input->getOption('web-dir') ?: false;
        $web_dir = $io->ask(
            'Public web directory [<comment>'.$web_dir.'</comment>]: ',
            $web_dir
        );
        $input->setOption('web-dir', $web_dir);

        $input->setOption('core', $this->getCore($input));

        $io->writeError(['', 'Define your dependencies.', '']);

        $question = 'Would you like to define your dependencies (require) now [<comment>yes</comment>]? ';
        $require = $input->getOption('require');
        $requirements = [];
        if ($require || $io->askConfirmation($question, true)) {
            $requirements = $this->determineRequirements($input, $output, $require);
        }
        $input->setOption('require', $requirements);

        $question = 'Would you like to define your dev dependencies (require-dev) now [<comment>yes</comment>]? ';
        $requireDev = $input->getOption('require-dev');
        $devRequirements = [];
        if ($requireDev || $io->askConfirmation($question, true)) {
            $devRequirements = $this->determineRequirements($input, $output, $requireDev);
        }
        $input->setOption('require-dev', $devRequirements);
    }

    protected function getCore(InputInterface $input)
    {
        $io = $this->getIO();

        $core = $input->getOption('core');
        if ($this->isDrupal7($input) && $core == 'drupal/core') {
            $core = 'drupal/drupal';
        }
        $name_version_pair = $this->normalizeRequirements((array) $core);
        $core_package = $name_version_pair[0]['name'];

        $core_package = $io->askAndValidate(
            'Drupal core or distribution [<comment>'.$core_package.'</comment>]: ',
            function ($value) {
                $packages = $this->findPackages($value);
                foreach ($packages as $package) {
                    if ($package['name'] == $value) {
                        return $value;
                    }
                }
                throw new \Exception('Package not found');
            },
            null,
            $core_package
        );

        $pool = new Pool($input->getOption('stability'));
        $pool->addRepository($this->getRepos());
        $versionSelector = new VersionSelector($pool);
        $package = $versionSelector->findBestCandidate($core_package);
        $core_version = $package ? $versionSelector->findRecommendedRequireVersion($package) : '';

        $core_version = $io->ask(
            'Version for '.$core_package.' [<comment>'.$core_version.'</comment>]: ',
            $core_version
        );

        return $core_package.' '.$core_version;
    }
}
