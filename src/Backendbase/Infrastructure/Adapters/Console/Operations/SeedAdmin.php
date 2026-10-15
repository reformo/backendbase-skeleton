<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Console\Operations;

use Backendbase\Domain\IdentityAndAccess\Adapters\Console\LocalOperatorAuthority;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Seeders\AdminSeeder;
use Backendbase\Shared\Domain\Exception\DomainException;
use Backendbase\Shared\Exception\InvalidUserInput;
use Closure;
use Doctrine\DBAL\Exception as DatabaseException;
use JsonException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function function_exists;
use function is_file;
use function is_readable;
use function is_string;
use function posix_geteuid;

use const PHP_SAPI;

final class SeedAdmin extends Command
{
    /** @var Closure(): void */
    private readonly Closure $authorize;

    /** @param (Closure(): void)|null $authorize */
    public function __construct(private readonly AdminSeeder $seeder, Closure|null $authorize = null)
    {
        $this->authorize = $authorize ?? static fn () => LocalOperatorAuthority::assertRoot(PHP_SAPI, function_exists('posix_geteuid') ? posix_geteuid() : null);

        parent::__construct('database:seed-admin');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Create the approved initial Admin once. Preview is the default.');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Apply the seed from an authorized local root CLI process.');
        $this->addOption('password-file', null, InputOption::VALUE_REQUIRED, 'Read the initial password from this file without printing it.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $input->getOption('apply')) {
            $output->writeln('Preview: seed Mehmet Korkmaz, ' . AdminSeeder::EMAIL . ', ' . AdminSeeder::MOBILE . '. No database record changed.');

            return self::SUCCESS;
        }

        try {
            $authorize = $this->authorize;
            $authorize();
            $password = self::password($input);
            $seeder   = $this->seeder;
            $result   = $seeder->seed($password);
        } catch (DomainException) {
            $output->writeln('<error>Admin seeding was rejected. Check local root authority, password input, and existing identity state.</error>');

            return self::INVALID;
        } catch (DatabaseException | JsonException) {
            $output->writeln('<error>Admin seeding failed. Check database availability and schema readiness. No migration was applied.</error>');

            return self::FAILURE;
        }

        $output->writeln(($result['created'] ? 'Created Admin ' : 'Admin already exists: ') . $result['actorId'] . '.');

        return self::SUCCESS;
    }

    private static function password(InputInterface $input): Password
    {
        $path = $input->getOption('password-file');
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw InvalidUserInput::create('Supply a readable password file.');
        }

        $contents = file_get_contents($path, false, null, 0, 513);

        return new Password($contents === false ? throw InvalidUserInput::create('The password file could not be read.') : $contents);
    }
}
