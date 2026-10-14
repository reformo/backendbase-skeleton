<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use Throwable;

use function usleep;

final readonly class DoctrineIdentityTransaction
{
    public function __construct(private Connection $connection, private IdentitySession $session)
    {
    }

    /**
     * @param callable(): TResult $work
     *
     * @return TResult
     *
     * @template TResult
     */
    public function run(callable $work): mixed
    {
        $connection = $this->connection;
        if ($connection->isTransactionActive()) {
            throw new LogicException('Identity transactions cannot nest inside another unit of work.');
        }

        $session = $this->session;
        $attempt = 0;
        while (true) {
            $attempt++;
            $session->open($connection);
            try {
                return $connection->transactional(static function () use ($work, $session): mixed {
                    $result  = $work();
                    $manager = $session->manager();
                    $manager->flush();

                    return $result;
                });
            } catch (RetryableException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }

                usleep(10000 * $attempt);
            } catch (UniqueConstraintViolationException | OptimisticLockException $exception) {
                throw IdentityConflict::create('The identity record conflicts with the current state.', previous: $exception);
            } catch (Throwable $exception) {
                throw $exception;
            } finally {
                $session->discard();
            }
        }
    }
}
