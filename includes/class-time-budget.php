<?php
/**
 * Time budget.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Wall-clock allowance shared by the checks of one request.
 */
final class Time_Budget {

	/**
	 * Total seconds.
	 *
	 * @var float
	 */
	private $seconds;

	/**
	 * Start time.
	 *
	 * @var float
	 */
	private $start;

	/**
	 * Clock returning the current time in seconds.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param float         $seconds Allowance in seconds.
	 * @param callable|null $clock   Optional clock (tests).
	 */
	public function __construct( float $seconds, ?callable $clock = null ) {
		$this->seconds = $seconds;
		$this->clock   = null !== $clock ? $clock : 'microtime';
		$this->start   = $this->now();
	}

	/**
	 * A budget that never runs out.
	 *
	 * @return self
	 */
	public static function unlimited(): self {
		return new self( PHP_FLOAT_MAX );
	}

	/**
	 * Seconds left (never negative).
	 *
	 * @return float
	 */
	public function remaining(): float {
		return max( 0.0, $this->seconds - ( $this->now() - $this->start ) );
	}

	/**
	 * Whether nothing is left.
	 *
	 * @return bool
	 */
	public function exhausted(): bool {
		return $this->remaining() <= 0;
	}

	/**
	 * Current time.
	 *
	 * @return float
	 */
	private function now(): float {
		if ( 'microtime' === $this->clock ) {
			return microtime( true );
		}
		return (float) call_user_func( $this->clock );
	}
}
