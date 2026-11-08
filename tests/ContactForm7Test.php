<?php
/**
 * Contact Form 7 integration tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Filters;
use FraudNova\WordPress\Form_Assessment;
use FraudNova\WordPress\Form_Check;
use FraudNova\WordPress\Integrations\Contact_Form_7;
use FraudNova\WordPress\Plugin;
use Mockery;

/**
 * Tests for Contact_Form_7.
 */
class ContactForm7Test extends TestCase {

	/**
	 * Form check mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $check;

	/**
	 * Integration.
	 *
	 * @var Contact_Form_7
	 */
	private $cf7;

	protected function setUp(): void {
		parent::setUp();
		$this->check = Mockery::mock( Form_Check::class );
		$this->cf7   = new Contact_Form_7( Plugin::instance(), $this->check );
	}

	/**
	 * Build a submission.
	 *
	 * @param array $posted Posted data.
	 * @param array $tags   Tags as name => basetype.
	 * @return \WPCF7_Submission
	 */
	private function submission( array $posted, array $tags = array( 'your-email' => 'email', 'your-name' => 'text' ) ): \WPCF7_Submission {
		$objs = array();
		foreach ( $tags as $name => $basetype ) {
			$objs[] = (object) array(
				'name'     => $name,
				'basetype' => $basetype,
			);
		}
		return new \WPCF7_Submission( $posted, new \WPCF7_ContactForm( $objs ) );
	}

	/**
	 * Blocked assessment.
	 *
	 * @param bool $test_mode Test mode.
	 * @return Form_Assessment
	 */
	private function blocked( bool $test_mode = false ): Form_Assessment {
		$a          = Form_Assessment::allowed();
		$a->blocked = ! $test_mode;
		$a->reasons = array( 'ip_proxy' );
		return $a;
	}

	public function test_registers_filter() {
		Filters\expectAdded( 'wpcf7_spam' )->once()->with( array( $this->cf7, 'filter' ), 10, 2 );
		$this->cf7->register();
		$this->assertNotFalse( has_filter( 'wpcf7_spam', array( $this->cf7, 'filter' ) ) );
	}

	public function test_finds_email_tag_by_basetype_and_flags_spam() {
		$s = $this->submission( array( 'your-name' => 'Bob', 'your-email' => 'bob@example.com' ) );
		$this->check->shouldReceive( 'assess' )->once()->with( 'cf7', 'bob@example.com' )->andReturn( $this->blocked() );
		$this->assertTrue( $this->cf7->filter( false, $s ) );
		$this->assertCount( 1, $s->spam_log );
		$this->assertSame( 'fraudnova', $s->spam_log[0]['agent'] );
		$this->assertSame( 'FraudNova: Proxy, VPN or Tor', $s->spam_log[0]['reason'] );
	}

	public function test_clean_submission_is_untouched() {
		$s = $this->submission( array( 'your-email' => 'bob@example.com' ) );
		$this->check->shouldReceive( 'assess' )->once()->andReturn( Form_Assessment::allowed() );
		$this->assertFalse( $this->cf7->filter( false, $s ) );
		$this->assertSame( array(), $s->spam_log );
	}

	public function test_already_spam_short_circuits() {
		$this->check->shouldNotReceive( 'assess' );
		$this->assertTrue( $this->cf7->filter( true, $this->submission( array() ) ) );
	}

	public function test_no_email_tag_still_assesses_ip_only() {
		$s = $this->submission( array( 'your-name' => 'Bob' ), array( 'your-name' => 'text' ) );
		$this->check->shouldReceive( 'assess' )->once()->with( 'cf7', '' )->andReturn( Form_Assessment::allowed() );
		$this->assertFalse( $this->cf7->filter( false, $s ) );
	}

	public function test_array_value_takes_first_and_skips_empty_tags() {
		$s = $this->submission(
			array( 'a' => '', 'b' => array( 'x@example.com', 'y@example.com' ) ),
			array( 'a' => 'email', 'b' => 'email' )
		);
		$this->check->shouldReceive( 'assess' )->once()->with( 'cf7', 'x@example.com' )->andReturn( Form_Assessment::allowed() );
		$this->assertFalse( $this->cf7->filter( false, $s ) );
	}

	public function test_test_mode_never_flags_spam() {
		$s = $this->submission( array( 'your-email' => 'bob@example.com' ) );
		$this->check->shouldReceive( 'assess' )->once()->andReturn( $this->blocked( true ) );
		$this->assertFalse( $this->cf7->filter( false, $s ) );
		$this->assertSame( array(), $s->spam_log );
	}

	public function test_missing_submission_fails_open() {
		$this->check->shouldNotReceive( 'assess' );
		$this->assertFalse( $this->cf7->filter( false, null ) );
	}
}
