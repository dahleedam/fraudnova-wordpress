<?php
/**
 * Form submission check.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Scores a form submitter (IP and email), logs the outcome and decides.
 */
class Form_Check {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Scorer override (tests).
	 *
	 * @var Scorer|null
	 */
	private $scorer = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Replace the scorer.
	 *
	 * @param Scorer $scorer Scorer.
	 * @return void
	 */
	public function set_scorer( Scorer $scorer ): void {
		$this->scorer = $scorer;
	}

	/**
	 * Assess a submission. Never throws: any failure allows the submission.
	 *
	 * @param string $source Source key (wp_registration, wc_registration, cf7).
	 * @param string $email  Submitted email, or an empty string.
	 * @return Form_Assessment
	 */
	public function assess( string $source, string $email ): Form_Assessment {
		try {
			return $this->run( $source, $email );
		} catch ( \Throwable $e ) {
			try {
				$this->plugin->log()->record(
					array(
						'source'  => $source,
						'type'    => 'form',
						'level'   => 'allowed',
						'action'  => 'allowed',
						'message' => $e->getMessage(),
					)
				);
			} catch ( \Throwable $ignored ) {
				unset( $ignored );
			}
			return Form_Assessment::allowed();
		}
	}

	/**
	 * Do the work.
	 *
	 * @param string $source Source key.
	 * @param string $email  Email.
	 * @return Form_Assessment
	 */
	private function run( string $source, string $email ): Form_Assessment {
		$settings = $this->plugin->settings();
		$scorer   = null !== $this->scorer ? $this->scorer : $this->plugin->scorer();
		$options  = array(
			'strictness' => (int) $settings->get( 'strictness' ),
			'budget'     => new Time_Budget( (float) apply_filters( 'fraudnova_form_time_budget', 6.0 ) ),
		);

		$ip      = '';
		$results = array();
		if ( $settings->get( 'form_check_ip' ) ) {
			$ip = Client_Ip::resolve( $settings );
			if ( '' !== $ip ) {
				$results[ Subject::IP ] = $scorer->score( Subject::IP, $ip, $options );
			}
		}
		if ( $settings->get( 'form_check_email' ) && '' !== trim( $email ) ) {
			$results[ Subject::EMAIL ] = $scorer->score( Subject::EMAIL, $email, $options );
		}

		$assessment = Form_Assessment::evaluate( $results, $settings );
		$level      = $assessment->blocked ? 'blocked' : ( $assessment->would_block && $assessment->test_mode ? 'test_mode' : 'allowed' );
		$labels     = array_map( array( Reasons::class, 'label' ), $assessment->reasons );

		if ( '' !== trim( $email ) ) {
			$subject = Subject::mask( Subject::EMAIL, $email );
		} else {
			$subject = '' !== $ip ? Subject::mask( Subject::IP, $ip ) : '';
		}

		$this->plugin->log()->record(
			array(
				'source'  => $source,
				'subject' => $subject,
				'type'    => 'form',
				'score'   => $assessment->score,
				'level'   => $level,
				'action'  => $assessment->blocked ? 'blocked' : 'allowed',
				'message' => implode( ', ', $labels ),
			)
		);

		return $assessment;
	}
}
