<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Closure;
use FrozenV1Plugin\StellarWP\CoreUpdateNotice\CoreUpdateNotice as FrozenV1Notice;
use Mockery;
use stdClass;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Register;
use StellarWP\CoreUpdateNotice\Tests\Doubles\NewerNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\OlderNotice;

/**
 * Every prefixed copy contributes to a global dismissal contest and, when eligible for the current
 * screen, a separate display contest.
 */
final class NoticeVersionContestTest extends TestCase {

	public function testASingleCandidateWins(): void {
		$notice = new CoreUpdateNotice();
		$winner = $notice->selectWinner( null );

		$this->assertSame( CoreUpdateNotice::NOTICE_VERSION, $winner['version'] );
		$this->assertSame( $notice, $winner['notice'] );
	}

	public function testTheNewerCandidateWinsRegardlessOfRegistrationOrder(): void {
		$older = new OlderNotice( [ 'heading' => 'FROM THE OLD PLUGIN' ] );
		$newer = new NewerNotice( [ 'heading' => 'FROM THE UPDATED PLUGIN' ] );

		$winner = $older->selectWinner( null );
		$winner = $newer->selectWinner( $winner );

		$this->assertSame( $newer, $winner['notice'] );

		$winner = $newer->selectWinner( null );
		$winner = $older->selectWinner( $winner );

		$this->assertSame( $newer, $winner['notice'] );
	}

	public function testOnlyTheSelectedInstanceRenders(): void {
		$this->stubDismissed( [] );
		$this->stubCoreUpdate( 'upgrade' );
		$this->stubRenderable();

		$older = new OlderNotice( [ 'heading' => 'FROM THE OLD PLUGIN' ] );
		$newer = new NewerNotice( [ 'heading' => 'FROM THE UPDATED PLUGIN' ] );

		$this->stubDisplayWinner( $newer, 2 );

		$this->assertSame( '', $this->render( $older ) );
		$this->assertStringContainsString( 'FROM THE UPDATED PLUGIN', $this->render( $newer ) );
	}

	public function testEqualVersionsKeepTheFirstCandidate(): void {
		$this->stubDismissed( [] );
		$this->stubCoreUpdate( 'upgrade' );
		$this->stubRenderable();

		$first  = new CoreUpdateNotice( [ 'heading' => 'FIRST PLUGIN' ] );
		$second = new CoreUpdateNotice( [ 'heading' => 'SECOND PLUGIN' ] );

		$winner = $first->selectWinner( null );
		$winner = $second->selectWinner( $winner );

		$this->assertSame( $first, $winner['notice'] );

		$this->stubDisplayWinner( $first, 2 );

		$this->assertStringContainsString( 'FIRST PLUGIN', $this->render( $first ) );
		$this->assertSame( '', $this->render( $second ) );
	}

	public function testCurrentCopyInteroperatesWithASeparatelyPrefixedFrozenV1Copy(): void {
		$current  = new CoreUpdateNotice();
		$frozenV1 = new FrozenV1Notice();

		$winner = $frozenV1->selectWinner( null );
		$winner = $current->selectWinner( $winner );

		$this->assertSame( $frozenV1, $winner['notice'] );

		$winner = $current->selectWinner( null );
		$winner = $frozenV1->selectWinner( $winner );

		$this->assertSame( $current, $winner['notice'] );
	}

	public function testInvalidCandidatesAreReplaced(): void {
		$notice  = new CoreUpdateNotice();
		$invalid = [
			true,
			[],
			['version' => '', 'notice' => new stdClass()],
			['version' => 99, 'notice' => new stdClass()],
			['version' => '99.0.0', 'notice' => 'not an object'],
		];

		foreach ( $invalid as $candidate ) {
			$winner = $notice->selectWinner( $candidate );

			$this->assertSame( CoreUpdateNotice::NOTICE_VERSION, $winner['version'] );
			$this->assertSame( $notice, $winner['notice'] );
		}
	}

	public function testExistingWinnerPayloadAndForeignObjectArePreserved(): void {
		$candidate = [
			'version'      => '99.0.0',
			'notice'       => new stdClass(),
			'future-field' => 'keep me',
		];

		$this->assertSame( $candidate, (new CoreUpdateNotice())->selectWinner( $candidate ) );
	}

	public function testAnUnregisteredCopyDoesNotRender(): void {
		Functions\expect( 'current_user_can' )->never();

		$this->assertSame( '', $this->render( new CoreUpdateNotice() ) );
	}

	public function testALosingCopyDoesNotHandleDismissal(): void {
		$_GET[ CoreUpdateNotice::DISMISS_ACTION ] = '9.9';

		$older = new OlderNotice();
		$newer = new NewerNotice();

		Filters\expectApplied( CoreUpdateNotice::HANDLER_WINNER_FILTER )
			->once()
			->with( null )
			->andReturn( $newer->selectWinner( null ) );
		Functions\expect( 'check_admin_referer' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'wp_safe_redirect' )->never();

		$older->handleDismissal();

		$this->assertFalse( $older->terminated );
	}

	/**
	 * @dataProvider registrationOrders
	 *
	 * @param class-string<CoreUpdateNotice> $firstClass
	 * @param class-string<CoreUpdateNotice> $secondClass
	 */
	public function testRegisteredCallbacksElectScopedRendererAndGlobalHandler(
		string $firstClass,
		bool $firstIsEligible,
		string $secondClass,
		bool $secondIsEligible,
		?string $expectedHeading
	): void {
		$handlerFilters     = [];
		$displayFilters     = [];
		$dismissalCallbacks = [];
		$renderCallbacks    = [];

		Filters\expectAdded( CoreUpdateNotice::HANDLER_WINNER_FILTER )
			->twice()
			->with( Mockery::on( $this->captureCallback( $handlerFilters ) ), 10, 1 );
		Filters\expectAdded( CoreUpdateNotice::DISPLAY_WINNER_FILTER )
			->twice()
			->with( Mockery::on( $this->captureCallback( $displayFilters ) ), 10, 1 );
		Actions\expectAdded( 'admin_init' )
			->twice()
			->with( Mockery::on( $this->captureCallback( $dismissalCallbacks ) ), 10, 1 );
		Actions\expectAdded( 'admin_notices' )
			->twice()
			->with( Mockery::on( $this->captureCallback( $renderCallbacks ) ), 10, 1 );

		$first            = new $firstClass([
			'heading' => $firstClass === NewerNotice::class ? 'FROM THE UPDATED PLUGIN' : 'FROM THE OLD PLUGIN',
		]);
		$second           = new $secondClass([
			'heading' => $secondClass === NewerNotice::class ? 'FROM THE UPDATED PLUGIN' : 'FROM THE OLD PLUGIN',
		]);
		$firstPageChecks  = 0;
		$secondPageChecks = 0;

		Register::notice(
			$first,
			static function () use ( &$firstPageChecks, $firstIsEligible ): bool {
				++$firstPageChecks;

				return $firstIsEligible;
			}
		);
		Register::notice(
			$second,
			static function () use ( &$secondPageChecks, $secondIsEligible ): bool {
				++$secondPageChecks;

				return $secondIsEligible;
			}
		);

		Filters\expectApplied( CoreUpdateNotice::DISPLAY_WINNER_FILTER )
			->twice()
			->with( null )
			->andReturnUsing(
				static function ( $winner ) use ( &$displayFilters ) {
					foreach ( $displayFilters as $callback ) {
						$winner = $callback( $winner );
					}

					return $winner;
				}
			);

		$this->stubDismissed( [] );
		$this->stubCoreUpdate( 'upgrade' );
		$this->stubRenderable();

		ob_start();
		foreach ( $renderCallbacks as $callback ) {
			$callback();
		}
		$output = (string) ob_get_clean();

		if ( $expectedHeading === null ) {
			$this->assertSame( '', $output );
		} else {
			$this->assertStringContainsString( $expectedHeading, $output );
			$this->assertSame( 1, substr_count( $output, '<div class="notice notice-warning is-dismissible">' ) );
			$this->assertStringNotContainsString(
				$expectedHeading === 'FROM THE UPDATED PLUGIN'
					? 'FROM THE OLD PLUGIN'
					: 'FROM THE UPDATED PLUGIN',
				$output
			);
		}

		$this->assertSame( 1, $firstPageChecks );
		$this->assertSame( 1, $secondPageChecks );

		$_GET[ CoreUpdateNotice::DISMISS_ACTION ] = '9.9';

		Filters\expectApplied( CoreUpdateNotice::HANDLER_WINNER_FILTER )
			->twice()
			->with( null )
			->andReturnUsing(
				static function ( $winner ) use ( &$handlerFilters ) {
					foreach ( $handlerFilters as $callback ) {
						$winner = $callback( $winner );
					}

					return $winner;
				}
			);

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( CoreUpdateNotice::DISMISS_ACTION . ':9.9' );
		Functions\when( 'remove_query_arg' )->justReturn( '/wp-admin/' );
		Functions\expect( 'update_option' )
			->once()
			->with( CoreUpdateNotice::DISMISSED_OPTION, [ '9.9' => true ], false );
		Functions\expect( 'wp_safe_redirect' )->once()->with( '/wp-admin/' );

		foreach ( $dismissalCallbacks as $callback ) {
			$callback();
		}

		$newer = $first instanceof NewerNotice ? $first : $second;
		$older = $first instanceof OlderNotice ? $first : $second;
		$this->assertInstanceOf( NewerNotice::class, $newer );
		$this->assertInstanceOf( OlderNotice::class, $older );
		$this->assertTrue( $newer->terminated );
		$this->assertFalse( $older->terminated );
		$this->assertSame( 1, $firstPageChecks );
		$this->assertSame( 1, $secondPageChecks );
	}

	/**
	 * @return array<string, array{
	 *     class-string<CoreUpdateNotice>, bool, class-string<CoreUpdateNotice>, bool, string|null
	 * }>
	 */
	public function registrationOrders(): array {
		return [
			'both eligible, older first'             => [
				OlderNotice::class,
				true,
				NewerNotice::class,
				true,
				'FROM THE UPDATED PLUGIN',
			],
			'both eligible, newer first'             => [
				NewerNotice::class,
				true,
				OlderNotice::class,
				true,
				'FROM THE UPDATED PLUGIN',
			],
			'only older eligible'                    => [
				OlderNotice::class,
				true,
				NewerNotice::class,
				false,
				'FROM THE OLD PLUGIN',
			],
			'only older eligible, registered second' => [
				NewerNotice::class,
				false,
				OlderNotice::class,
				true,
				'FROM THE OLD PLUGIN',
			],
			'neither eligible'                       => [
				OlderNotice::class,
				false,
				NewerNotice::class,
				false,
				null,
			],
		];
	}

	/**
	 * @param array<int, callable> $callbacks
	 */
	private function captureCallback( array &$callbacks ): Closure {
		return static function ( callable $callback ) use ( &$callbacks ): bool {
			$callbacks[] = $callback;

			return true;
		};
	}

}
