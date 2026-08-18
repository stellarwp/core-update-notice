<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Register;

final class RegisterTest extends TestCase {

	public function testItRegistersTheProvidedNoticeInstance(): void {
		$notice = new CoreUpdateNotice();

		Filters\expectAdded( CoreUpdateNotice::WINNER_FILTER )
			->once()
			->with( [ $notice, 'selectWinner' ], 10, 1 );
		Actions\expectAdded( 'admin_init' )
			->once()
			->with( [ $notice, 'handleDismissal' ], 10, 1 );
		Actions\expectAdded( 'admin_notices' )
			->once()
			->with( [ $notice, 'render' ], 10, 1 );

		Register::notice( $notice );
	}

	public function testItRejectsRegistrationAfterAdminInit(): void {
		Actions\expectDone( 'admin_init' )->once();
		Filters\expectAdded( CoreUpdateNotice::WINNER_FILTER )->never();
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

		Register::notice( new CoreUpdateNotice() );
	}

	public function testItRejectsRegistrationDuringAdminInit(): void {
		$notice = new CoreUpdateNotice();

		Filters\expectAdded( CoreUpdateNotice::WINNER_FILTER )->never();
		Actions\expectAdded( 'admin_init' )->never();
		Actions\expectAdded( 'admin_notices' )->never();
		Functions\expect( '_doing_it_wrong' )->once();
		Actions\expectDone( 'admin_init' )
			->once()
			->whenHappen(static function () use ($notice): void {
				Register::notice( $notice );
			});

		do_action( 'admin_init' );
	}

}
