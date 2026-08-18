<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Closure;
use Mockery;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Register;

final class RegisterTest extends TestCase {

	public function testItRegistersTheProvidedNoticeInstance(): void {
		$notice        = new CoreUpdateNotice();
		$displayFilter = null;

		Filters\expectAdded( CoreUpdateNotice::HANDLER_WINNER_FILTER )
			->once()
			->with( [ $notice, 'selectWinner' ], 10, 1 );
		Filters\expectAdded( CoreUpdateNotice::DISPLAY_WINNER_FILTER )
			->once()
			->with(
				Mockery::on(static function ( callable $callback ) use ( &$displayFilter ): bool {
					$displayFilter = $callback;

					return true;
				}),
				10,
				1
			);
		Actions\expectAdded( 'admin_init' )
			->once()
			->with( [ $notice, 'handleDismissal' ], 10, 1 );
		Actions\expectAdded( 'admin_notices' )
			->once()
			->with( [ $notice, 'render' ], 10, 1 );

		Register::notice( $notice, static fn(): bool => true );

		$this->assertInstanceOf( Closure::class, $displayFilter );
		$this->assertSame( $notice, $displayFilter( null )['notice'] );
	}

	public function testItRejectsRegistrationAfterAdminInit(): void {
		Actions\expectDone( 'admin_init' )->once();
		Filters\expectAdded( CoreUpdateNotice::HANDLER_WINNER_FILTER )->never();
		Filters\expectAdded( CoreUpdateNotice::DISPLAY_WINNER_FILTER )->never();
		Actions\expectAdded( 'admin_init' )->never();
		Actions\expectAdded( 'admin_notices' )->never();
		Functions\expect( '_doing_it_wrong' )
			->once()
			->with(
				Register::class . '::notice',
				'Core update notices must be registered before admin_init.',
				CoreUpdateNotice::NOTICE_VERSION
			);

		do_action( 'admin_init' );

		Register::notice( new CoreUpdateNotice(), static fn(): bool => true );
	}

	public function testItRejectsRegistrationDuringAdminInit(): void {
		$notice = new CoreUpdateNotice();

		Filters\expectAdded( CoreUpdateNotice::HANDLER_WINNER_FILTER )->never();
		Filters\expectAdded( CoreUpdateNotice::DISPLAY_WINNER_FILTER )->never();
		Actions\expectAdded( 'admin_init' )->never();
		Actions\expectAdded( 'admin_notices' )->never();
		Functions\expect( '_doing_it_wrong' )->once();
		Actions\expectDone( 'admin_init' )
			->once()
			->whenHappen(static function () use ( $notice ): void {
				Register::notice( $notice, static fn(): bool => true );
			});

		do_action( 'admin_init' );
	}

}
