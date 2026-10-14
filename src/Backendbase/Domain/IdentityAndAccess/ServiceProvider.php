<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess;

final class ServiceProvider implements \Backendbase\Shared\ServiceProvider
{
    /** @return iterable<class-string, class-string> */
    public static function getDefinitions(): iterable
    {
        return [
            Contracts\IdentityReadRepository::class => Adapters\ReadModel\DoctrineIdentityReadRepository::class,
            Contracts\IdentityStore::class => Adapters\Persistence\Doctrine\DoctrineIdentityStore::class,
            Contracts\CredentialStore::class => Adapters\Persistence\Doctrine\DoctrineCredentialStore::class,
            Contracts\OperatorStore::class => Adapters\Persistence\Operator\DoctrineOperatorStore::class,
            Contracts\AuthenticationSecrets::class => Adapters\Authentication\SodiumAuthenticationSecrets::class,
            Contracts\TokenCodec::class => Adapters\Authentication\RealmTokenCodec::class,
            Contracts\TokenRegistry::class => Adapters\Authentication\RedisTokenRegistry::class,
            Contracts\OwnershipNotifier::class => Adapters\Notification\OwnershipMailer::class,
            Contracts\IdentityOperationResults::class => Adapters\ReadModel\RequestIdentityResults::class,
        ];
    }

    /** @return array<class-string, class-string> */
    public static function getHandlers(): array
    {
        return [
            Contracts\Command\Operations\ApplyOperatorAction::class => Application\CommandHandlers\Operations\ApplyOperatorActionHandler::class,
            Contracts\Command\Authentication\RegisterUser::class => Application\CommandHandlers\Registration\RegisterUserHandler::class,
            Contracts\Command\Authentication\VerifyOwnership::class => Application\CommandHandlers\Enrollment\VerifyOwnershipHandler::class,
            Contracts\Command\Authentication\ResendOwnership::class => Application\CommandHandlers\Enrollment\ResendOwnershipHandler::class,
            Contracts\Command\Authentication\PrepareTotpEnrollment::class => Application\CommandHandlers\Enrollment\PrepareTotpEnrollmentHandler::class,
            Contracts\Command\Authentication\CompleteTotpEnrollment::class => Application\CommandHandlers\Enrollment\CompleteTotpEnrollmentHandler::class,
            Contracts\Command\Authentication\BeginLogin::class => Application\CommandHandlers\Login\BeginLoginHandler::class,
            Contracts\Command\Authentication\CompleteLogin::class => Application\CommandHandlers\Login\CompleteLoginHandler::class,
            Contracts\Command\Lifecycle\RevokeAccessToken::class => Application\CommandHandlers\Lifecycle\RevokeAccessTokenHandler::class,
            Contracts\Command\Lifecycle\Logout::class => Application\CommandHandlers\Lifecycle\LogoutHandler::class,
            Contracts\Command\Lifecycle\CloseAccount::class => Application\CommandHandlers\Lifecycle\CloseAccountHandler::class,
            Contracts\Command\Administration\InviteAdmin::class => Application\CommandHandlers\Administration\InviteAdminHandler::class,
            Contracts\Command\Administration\ApproveAdmin::class => Application\CommandHandlers\Administration\ApproveAdminHandler::class,
            Contracts\Command\Administration\ChangeActor::class => Application\CommandHandlers\Administration\ChangeActorHandler::class,
            Contracts\Query\GetActorHistory::class => Application\QueryHandlers\GetActorHistoryHandler::class,
            Contracts\Query\GetIdentityOperation::class => Application\QueryHandlers\GetIdentityOperationHandler::class,
        ];
    }

    /** @return iterable<int, array{events: array<int, string>, subscriberFQCN: class-string, messageFQCN?: class-string, eventVersion?: string}> */
    public static function getIntegrationEventSubscribers(): iterable
    {
        return [];
    }
}
