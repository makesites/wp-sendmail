<?php

/*
	Plugin Name: Sendmail
	Plugin URI: http://makesites.org
	Description: Basic handling of email submissions
	Version: 1.0
	Author: Makesites
	Author URI: http://makesites.org
 */


$wp_sendmail = new WP_Sendmail();

class WP_Sendmail {

	protected $version = "1.0"; // pickup version from comments?

	function __construct() {

		// hooks
		add_action( 'init', array($this, 'process_post') );

	}

	function process_post() {
		// prerequisites
		// - escape all irrelevant submissions
		if ( !isset( $_POST['wp-sendmail'] ) ) return;
		// - accept submissions from the same domain only
		if( !isset($_SERVER['HTTP_REFERER'] )) return;
		$referer = parse_url( $_SERVER['HTTP_REFERER'] );
		if( $referer['host'] !== $_SERVER["HTTP_HOST"] ) return; // assume HTTP_HOST?
		// all fields are required
		if( empty($_POST["from"]) || empty($_POST["to"]) || empty($_POST["subject"]) || empty($_POST["message"]) ) return; // return $this->error('empty_fields');

		// basic set - sanitize form values
		$data = array(
			'from' => sanitize_email( $_POST["from"] ),
			'to' => sanitize_email( $_POST["to"] ),
			'subject' => sanitize_text_field( $_POST["subject"] ),
			'message' => esc_textarea( $_POST["message"] )
		);
		// optional fields
		if( array_key_exists('fullname', $_POST) ) $data['fullname'] = sanitize_email( $_POST["fullname"] );

		// send email
		$this->send( $data );
	}

	// redirect the user if needed
	protected function send( $data ) {
		// error check?
		// headers
		$headers = ( isset($data['fullname']) )
			? "From: ". $data['fullname'] ." <". $data['from'] .">" ." \r\n"
			: "From:". $data['from'] ." \r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html\r\n";

		$submit = mail( $data['to'], $data['subject'], $data['message'], $headers );

		if( $submit == true ) {
			$GLOBALS['wp_sendmail_result'] = "Message sent successfully...";
		} else {
			$GLOBALS['wp_sendmail_result'] = "Message could not be sent...";
		}

	}

}

?>
