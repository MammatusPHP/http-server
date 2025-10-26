<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mammatus\Http\Server\Composer\Installer;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function copy;
use function dirname;
use function file_exists;
use function fopen;
use function fseek;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function opendir;
use function readdir;
use function stream_get_contents;
use function touch;

use const DIRECTORY_SEPARATOR;

final class InstallerTest extends TestCase
{
    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertSame([ScriptEvents::PRE_AUTOLOAD_DUMP => ['findServers']], Installer::getSubscribedEvents());
    }

    #[Test]
    public function generate(): void
    {
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => [
                'vendor-dir' => $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR,
            ],
        ]);
        $rootPackage = new RootPackage('mammatus/http-server', 'dev-master', 'dev-master');
        $rootPackage->setExtra([
            'mammatus' => [
                'http' => [
                    'server' => ['has-vhosts' => true],
                ],
            ],
        ]);
        $rootPackage->setAutoload([
            'psr-4' => [
                'Mammatus\\Http\\Server\\' => 'src',
                'Mammatus\\DevApp\\Http\\Server\\' => 'etc/dev-app',
            ],
        ]);

        $io         = new class () extends NullIO {
            private readonly StreamOutput $output;

            public function __construct()
            {
                /** @phpstan-ignore argument.type */
                $this->output = new StreamOutput(fopen('php://memory', 'rw'), decorated: false);
            }

            public function output(): string
            {
                fseek($this->output->getStream(), 0);

                return stream_get_contents($this->output->getStream());
            }

            /**
             * @inheritDoc
             * @phpstan-ignore typeCoverage.paramTypeCoverage
             */
            public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
            {
                $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
            }
        };
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(
            ScriptEvents::PRE_AUTOLOAD_DUMP,
            $composer,
            $io,
        );

        $installer = new Installer();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io);
        $installer->deactivate($composer, $io);
        $installer->uninstall($composer, $io);

        $this->recurseCopy(dirname(__DIR__, 2) . '/', $this->getTmpDir());

        $fileNameList    = $this->getTmpDir() . 'src/Generated/AbstractList.php';
        $fileNameManager = $this->getTmpDir() . 'src/Generated/Manager.php';
        $sneakyFile      = $this->getTmpDir() . 'src' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR . 'sneaky.file';
        touch($sneakyFile);

        self::assertFileExists($sneakyFile);

        // Do the actual generating
        Installer::findServers($event);

        self::assertFileDoesNotExist($sneakyFile);

        $output = $io->output();

        self::assertStringContainsString('<info>mammatus/http-server:</info> Locating Virtual Hosts', $output);
        self::assertStringContainsString('<info>mammatus/http-server:</info> Generated Virtual Host(s) config in ', $output);
        self::assertStringContainsString('<info>mammatus/http-server:</info> Found 6 Virtual Host(s)', $output);
        //self::assertStringContainsString('<error>mammatus/cron:</error> An error occurred:  Cannot reflect "<fg=cyan>Mammatus\Cron\Manager</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "Mammatus\Cron\Generated\AbstractManager" could not be found in the located source</>', $output);

//        self::assertFileExists($fileNameList);
//        self::assertFileExists($fileNameManager);
//        self::assertTrue(in_array(
//            substr(sprintf('%o', fileperms($fileNameList)), -4),
//            [
//                '0764',
//                '0664',
//                '0666',
//            ],
//            true,
//        ));
//        self::assertTrue(in_array(
//            substr(sprintf('%o', fileperms($fileNameManager)), -4),
//            [
//                '0764',
//                '0664',
//                '0666',
//            ],
//            true,
//        ));
//        $fileContentsList = file_get_contents($fileNameList);
//        self::assertStringContainsStringIgnoringCase('* @see \\' . Noop::class, $fileContentsList);
//        self::assertStringContainsStringIgnoringCase('yield \'no.op-Mammatus-DevApp-Cron-Noop\' => new \Mammatus\Cron\Action(', $fileContentsList);
//        self::assertStringContainsStringIgnoringCase('addOns: \json_decode(\'[]\', true),', $fileContentsList);
//        self::assertStringNotContainsStringIgnoringCase('type: Type::Kubernetes,', $fileContentsList);
//        $fileContentsManager = file_get_contents($fileNameManager);
//        self::assertStringContainsStringIgnoringCase('* @see \\' . Noop::class . ' */', $fileContentsManager);
//        self::assertStringContainsStringIgnoringCase('new Cron\Action(', $fileContentsManager);
//        self::assertStringContainsStringIgnoringCase('fn () => $this->perform(\\' . Noop::class . '::class),', $fileContentsManager);
//        self::assertStringContainsStringIgnoringCase('cron_no.op', $fileContentsManager);
//        self::assertStringNotContainsStringIgnoringCase('cron_ye.et', $fileContentsManager);
//        self::assertStringNotContainsStringIgnoringCase('fn () => $this->perform(\\' . Yep::class . '::class),', $fileContentsManager);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        if (! file_exists($src)) {
            throw new RuntimeException('Source directory "' . $src . '" does not exist!');
        }

        $dir = opendir($src);

        if (! is_resource($dir)) {
            throw new RuntimeException('Unable to open source directory "' . $src . '"!');
        }

        if (! file_exists($dst)) {
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            if (is_dir($src . '/' . $file)) {
                $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } elseif (is_file($src . '/' . $file)) {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
