<?php
/**
 * Time budget tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use FraudNova\WordPress\Time_Budget;

/**
 * Time budget.
 */
final class TimeBudgetTest extends TestCase {

	public function test_remaining_and_exhausted_follow_the_clock(): void {
		$now    = 100.0;
		$budget = new Time_Budget(
			3.0,
			function () use ( &$now ) {
				return $now;
			}
		);
		$this->assertEqualsWithDelta( 3.0, $budget->remaining(), 0.0001 );
		$this->assertFalse( $budget->exhausted() );
		$now = 102.5;
		$this->assertEqualsWithDelta( 0.5, $budget->remaining(), 0.0001 );
		$now = 103.0;
		$this->assertTrue( $budget->exhausted() );
		$now = 110.0;
		$this->assertSame( 0.0, $budget->remaining() );
	}

	public function test_unlimited_is_never_exhausted(): void {
		$budget = Time_Budget::unlimited();
		$this->assertFalse( $budget->exhausted() );
		$this->assertGreaterThan( 1000000.0, $budget->remaining() );
	}
}
