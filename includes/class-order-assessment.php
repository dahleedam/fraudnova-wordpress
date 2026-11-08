<?php
/**
 * Order risk decision.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Turns check results into a level, reasons and an order action.
 */
final class Order_Assessment {

	const LEVEL_CLEAN   = 'clean';
	const LEVEL_REVIEW  = 'review';
	const LEVEL_HIGH    = 'high';
	const LEVEL_UNKNOWN = 'unknown';

	const ACTION_NONE   = 'none';
	const ACTION_FLAG   = 'flag';
	const ACTION_HOLD   = 'hold';
	const ACTION_CANCEL = 'cancel';

	/**
	 * Overall level.
	 *
	 * @var string
	 */
	public string $level = self::LEVEL_UNKNOWN;

	/**
	 * Highest score, or null.
	 *
	 * @var int|null
	 */
	public ?int $score = null;

	/**
	 * Reason keys.
	 *
	 * @var string[]
	 */
	public array $reasons = array();

	/**
	 * Decided action (after test mode and paid-by-card).
	 *
	 * @var string
	 */
	public string $action = self::ACTION_NONE;

	/**
	 * Action before degradation.
	 *
	 * @var string
	 */
	public string $requested_action = self::ACTION_NONE;

	/**
	 * Whether test mode was on.
	 *
	 * @var bool
	 */
	public bool $test_mode = false;

	/**
	 * Whether the order was paid by card.
	 *
	 * @var bool
	 */
	public bool $paid_by_card = false;

	/**
	 * Use the named constructors.
	 */
	private function __construct() {}

	/**
	 * Rank of a level for comparison.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	private static function rank( string $level ): int {
		$ranks = array(
			self::LEVEL_UNKNOWN => 0,
			self::LEVEL_CLEAN   => 1,
			self::LEVEL_REVIEW  => 2,
			self::LEVEL_HIGH    => 3,
		);
		return isset( $ranks[ $level ] ) ? $ranks[ $level ] : 0;
	}

	/**
	 * The worse of two levels.
	 *
	 * @param string $a Level.
	 * @param string $b Level.
	 * @return string
	 */
	private static function worst( string $a, string $b ): string {
		return self::rank( $b ) > self::rank( $a ) ? $b : $a;
	}

	/**
	 * Decide.
	 *
	 * @param array    $results      Map of type => Check_Result.
	 * @param Settings $settings     Settings.
	 * @param bool     $paid_by_card Whether the order was paid by card.
	 * @return self
	 */
	public static function evaluate( array $results, Settings $settings, bool $paid_by_card = false ): self {
		$a               = new self();
		$a->test_mode    = $settings->test_mode();
		$a->paid_by_card = $paid_by_card;
		$a->score        = Reasons::max_score( $results );

		$counted = false;
		$errored = false;
		foreach ( $results as $result ) {
			if ( ! $result instanceof Check_Result ) {
				continue;
			}
			if ( $result->counted() ) {
				$counted = true;
			} elseif ( Check_Result::STATUS_ERROR === $result->status ) {
				$errored = true;
			}
		}

		$level   = $counted ? self::LEVEL_CLEAN : self::LEVEL_UNKNOWN;
		$reasons = array();

		if ( null !== $a->score ) {
			if ( $a->score >= (int) $settings->get( 'hold_threshold' ) ) {
				$level     = self::LEVEL_HIGH;
				$reasons[] = 'score_' . $a->score;
			} elseif ( $a->score >= (int) $settings->get( 'review_threshold' ) ) {
				$level     = self::LEVEL_REVIEW;
				$reasons[] = 'score_' . $a->score;
			}
		}

		$fired = Reasons::signals( $results );
		$rules = array(
			'phone_invalid'    => array( 'phone_invalid' ),
			'phone_voip'       => array( 'phone_voip' ),
			'email_disposable' => array( 'email_disposable', 'email_invalid' ),
			'ip_proxy'         => array( 'ip_proxy' ),
			'ip_abuse'         => array( 'ip_abuse' ),
			'email_abuse'      => array( 'email_abuse' ),
		);
		foreach ( $rules as $rule => $signals ) {
			$setting = (string) $settings->get( 'rule_' . $rule );
			if ( self::LEVEL_REVIEW !== $setting && self::LEVEL_HIGH !== $setting ) {
				continue;
			}
			if ( array() === array_intersect( $signals, $fired ) ) {
				continue;
			}
			$reasons[] = $rule;
			$level     = self::worst( $level, $setting );
		}

		if ( $errored && 'review' === $settings->get( 'on_error' ) && self::rank( $level ) < self::rank( self::LEVEL_REVIEW ) ) {
			$level     = self::LEVEL_REVIEW;
			$reasons[] = 'api_error';
		}

		$a->level   = $level;
		$a->reasons = $reasons;

		$requested = self::ACTION_NONE;
		if ( self::LEVEL_REVIEW === $level ) {
			$requested = (string) $settings->get( 'review_action' );
		} elseif ( self::LEVEL_HIGH === $level ) {
			$requested = (string) $settings->get( 'high_action' );
		}
		$a->requested_action = $requested;

		$action = $requested;
		if ( $paid_by_card && self::is_destructive( $action ) ) {
			$action = self::ACTION_FLAG;
		}
		if ( $a->test_mode && self::is_destructive( $action ) ) {
			$action = self::ACTION_FLAG;
		}
		$a->action = $action;

		return $a;
	}

	/**
	 * Whether an action holds or cancels the order.
	 *
	 * @param string $action Action.
	 * @return bool
	 */
	private static function is_destructive( string $action ): bool {
		return self::ACTION_HOLD === $action || self::ACTION_CANCEL === $action;
	}

	/**
	 * Order note text.
	 *
	 * @return string
	 */
	public function note(): string {
		if ( null === $this->score ) {
			$head = __( 'FraudNova: no check could be completed', 'fraudnova' );
			if ( self::LEVEL_UNKNOWN !== $this->level ) {
				$head .= ' (' . $this->level . ')';
			}
		} else {
			$head = sprintf(
				/* translators: 1: fraud score, 2: level. */
				__( 'FraudNova: score %1$d (%2$s)', 'fraudnova' ),
				$this->score,
				$this->level
			);
		}

		if ( array() !== $this->reasons ) {
			$labels = array_map( array( Reasons::class, 'label' ), $this->reasons );
			$head  .= ' — ' . implode( ', ', $labels );
		}
		$note = $head . '.';

		$phrases = array(
			self::ACTION_FLAG   => __( 'Flagged for review.', 'fraudnova' ),
			self::ACTION_HOLD   => __( 'Held for review.', 'fraudnova' ),
			self::ACTION_CANCEL => __( 'Cancelled.', 'fraudnova' ),
		);
		if ( isset( $phrases[ $this->action ] ) ) {
			$note .= ' ' . $phrases[ $this->action ];
		}

		if ( self::is_destructive( $this->requested_action ) ) {
			if ( $this->test_mode ) {
				$note .= ' ' . __( '(test mode, no action taken)', 'fraudnova' );
			}
			if ( $this->paid_by_card ) {
				$note .= ' ' . __( '(paid by card, flagged only)', 'fraudnova' );
			}
		}

		return $note;
	}

	/**
	 * Storable form.
	 *
	 * @return array
	 */
	public function to_meta(): array {
		return array(
			'level'            => $this->level,
			'score'            => $this->score,
			'reasons'          => $this->reasons,
			'action'           => $this->action,
			'requested_action' => $this->requested_action,
			'test_mode'        => $this->test_mode,
			'paid_by_card'     => $this->paid_by_card,
		);
	}

	/**
	 * Rebuild from stored meta (tolerates missing keys).
	 *
	 * @param array $m Stored meta.
	 * @return self
	 */
	public static function from_meta( array $m ): self {
		$a                   = new self();
		$a->level            = isset( $m['level'] ) ? (string) $m['level'] : self::LEVEL_UNKNOWN;
		$a->score            = isset( $m['score'] ) && is_numeric( $m['score'] ) ? (int) $m['score'] : null;
		$a->reasons          = isset( $m['reasons'] ) && is_array( $m['reasons'] ) ? array_map( 'strval', array_values( $m['reasons'] ) ) : array();
		$a->action           = isset( $m['action'] ) ? (string) $m['action'] : self::ACTION_NONE;
		$a->requested_action = isset( $m['requested_action'] ) ? (string) $m['requested_action'] : $a->action;
		$a->test_mode        = ! empty( $m['test_mode'] );
		$a->paid_by_card     = ! empty( $m['paid_by_card'] );
		return $a;
	}
}
