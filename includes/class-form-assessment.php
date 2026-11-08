<?php
/**
 * Form submission risk decision.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a form submission is blocked.
 */
final class Form_Assessment {

	/**
	 * Whether the submission is blocked (never true in test mode).
	 *
	 * @var bool
	 */
	public bool $blocked = false;

	/**
	 * Whether it would be blocked outside test mode.
	 *
	 * @var bool
	 */
	public bool $would_block = false;

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
	 * Whether test mode was on.
	 *
	 * @var bool
	 */
	public bool $test_mode = false;

	/**
	 * Use evaluate().
	 */
	private function __construct() {}

	/**
	 * Decide.
	 *
	 * @param array    $results  Map of type => Check_Result.
	 * @param Settings $settings Settings.
	 * @return self
	 */
	public static function evaluate( array $results, Settings $settings ): self {
		$a            = new self();
		$a->test_mode = $settings->test_mode();
		$a->score     = Reasons::max_score( $results );

		if ( null !== $a->score && $a->score >= (int) $settings->get( 'form_block_threshold' ) ) {
			$a->reasons[] = 'score_' . $a->score;
		}

		$fired = Reasons::signals( $results );
		$rules = array(
			array( 'form_rule_email_disposable', 'email_disposable', 'email_disposable' ),
			array( 'form_rule_email_invalid', 'email_invalid', 'email_disposable' ),
			array( 'form_rule_ip_proxy', 'ip_proxy', 'ip_proxy' ),
			array( 'form_rule_ip_abuse', 'ip_abuse', 'ip_abuse' ),
		);
		foreach ( $rules as $rule ) {
			if ( $settings->get( $rule[0] ) && in_array( $rule[1], $fired, true ) && ! in_array( $rule[2], $a->reasons, true ) ) {
				$a->reasons[] = $rule[2];
			}
		}

		$a->would_block = array() !== $a->reasons;
		$a->blocked     = $a->would_block && ! $a->test_mode;
		return $a;
	}
}
