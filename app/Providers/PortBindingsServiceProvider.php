<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\Transactions;
use App\Application\Ports\UserRepository;
use App\Infrastructure\Persistence\DbBookRepository;
use App\Infrastructure\Persistence\DbCategoryRepository;
use App\Infrastructure\Persistence\DbLoanRepository;
use App\Infrastructure\Persistence\DbMemberRepository;
use App\Infrastructure\Persistence\DbUserRepository;
use App\Infrastructure\Security\Hs256TokenIssuer;
use App\Infrastructure\Security\LaravelPasswordHasher;
use App\Infrastructure\Storage\LocalCoverStorage;
use App\Infrastructure\System\LaravelTransactions;
use App\Infrastructure\System\SystemClock;
use App\Infrastructure\System\UuidGenerator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/** Aquí —y solo aquí— cada puerto se encuentra con su adaptador. */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidGenerator::class);
        $this->app->bind(Transactions::class, LaravelTransactions::class);
        $this->app->bind(CoverStorage::class, LocalCoverStorage::class);

        $this->app->bind(
            PasswordHasher::class,
            static fn (): PasswordHasher => new LaravelPasswordHasher((string) config('library.hash_driver')),
        );
        $this->app->bind(
            TokenIssuer::class,
            static fn (Application $app): TokenIssuer => new Hs256TokenIssuer(
                (string) config('library.jwt_secret'),
                (int) config('library.jwt_ttl_seconds'),
                $app->make(Clock::class),
            ),
        );

        $this->app->bind(UserRepository::class, DbUserRepository::class);
        $this->app->bind(CategoryRepository::class, DbCategoryRepository::class);
        $this->app->bind(BookRepository::class, DbBookRepository::class);
        $this->app->bind(MemberRepository::class, DbMemberRepository::class);
        $this->app->bind(LoanRepository::class, DbLoanRepository::class);
    }
}
