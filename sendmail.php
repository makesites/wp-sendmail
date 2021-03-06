<?php

/*
	Plugin Name: Sendmail
	Plugin URI: http://makesites.org
	Description: Basic handling of email submissions
	Version: 1.2.0
	Author: Makesites
	Author URI: http://makesites.org
 */


$wp_sendmail = new WP_Sendmail();

class WP_Sendmail {

	protected $version = "1.2.0"; // pickup version from comments?
	protected $db = "sendmail_log";
	protected $page = "sendmail-report";

	function __construct() {

		// hooks
		add_action( 'init', array($this, 'process_post') );
		add_action( 'init', array($this, 'session_start'), 1);
		add_action( 'plugins_loaded', array($this, 'db_check') );
		// admin
		add_action( 'admin_init', array($this, 'process_download') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
	}

	function admin_menu(){
		add_menu_page( 'Sendmail: Report', 'Sendmail', 'administrator', 'sendmail-report', array($this, 'admin_page') );
	}

	function admin_page(){
		global $wpdb;
		// variables
		$num = 30; // items per page
		$page = ( isset( $_GET['results'] )) ? (int)$_GET['results'] : 1;
		$table_name = $wpdb->prefix . $this->db;

		$results = $wpdb->get_results("SELECT * FROM ". $table_name ." LIMIT ". ($page-1) * $num .",". $num );
		$all = $wpdb->get_var( "SELECT COUNT(*) FROM ". $table_name );
		$pages = ceil( $all / $num );
		//

		// load view
		$view = dirname(__FILE__) . "/views/admin.php";

		return include( $view );

	}

	function db_check() {
		if ( get_site_option( 'wp_sendmail' ) != $this->version ) {
			$this->setup();
		}
	}

	// export data
	function export(){
		global $wpdb;
		// variables
		$data = "";
		$timestamp = date('Ymd-His');
		$table_name = $wpdb->prefix . $this->db;
		$filename = "sendmail-export";
		/* //Method #1 (permission issues)
		$dir = $path = sys_get_temp_dir(); //get_home_path() ."wp-content/cache";
		// delete previous export
		if( file_exists("$dir/$filename.csv") ) unlink("$dir/$filename.csv");
		// query
		$sql = "SELECT * INTO OUTFILE '$dir/$filename.csv' ";
		$sql .= "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '". '"' ."' ";
		$sql .= 'LINES TERMINATED BY "\n" ';
		$sql .= "FROM $table_name;";

		$e = $wpdb->query($sql);
		// error control?
		//var_dump($e);

		// output data
		$data = file_get_contents("$dir/$filename.csv");
		*/

		// get data
		$sql = "SELECT * FROM $table_name";

		$results = $wpdb->get_results($sql);
		// special case for meta
		foreach( $results as $row ) {
			// breakup
			if( !isset($row->meta) ) continue;
			$pairs = explode(',', $row->meta); // assume string?
			foreach( $pairs as $pair ){
				if( empty($pair) ) continue;
				$v = explode(":", $pair);
				$row->$v[0] = $v[1];
			}
			// delete the meta values
			unset( $row->meta );
		}

		// get column names from rows
		$columns = array();
		foreach( $results as $row ) {
			$keys = array_keys(get_object_vars($row)); // convert object to array, reset keys
			// fix formatting
			$keys= array_map(function($word) { return ucwords( str_replace("_", " ", $word ) ); }, $keys);
			$columns = array_merge($columns, $keys);
		}
		$columns = array_unique( $columns );
		$data = implode(",", $columns)  . "\n";

		// write file
		foreach( $results as $row ) {
			$line = '';
			$values = array_values(get_object_vars($row)); // convert object to array, reset keys
			$length = count($values);
			//
			foreach( $values as $i => $value ) {
				if ( ( !isset( $value ) ) || ( $value == "" ) ) {
					$value = ",";
				} else {
					$value = str_replace( '"' , '""' , $value );
					$value = '"' . $value . '"';
					if( ($i+1) < $length ) $value .= ",";
				}
				$line .= $value;
			}
			$data .= trim( $line ) . "\n";
		}
		//
		$data = str_replace( "\r" , "" , $data );

		if ( $data == "" ) {
			$data = "\n(0) Records Found!\n";
		}

		// download headers
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=$filename". "-". $timestamp .".csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		print "$data";
		//
		exit;
	}

	// add a new line in the logs
	function log( $data=array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->db;
		// add timestamp
		$data['timestamp'] = current_time( 'mysql' );
		$data['session'] = session_id();

		$wpdb->insert( $table_name, $data );

	}

	function process_download(){
		$download = ( isset( $_GET['page'] ) &&  $_GET['page'] == $this->page && isset( $_GET['download'] ) );

		// go to export if we're downloading
		if( $download ) return $this->export();

	}

	function process_post() {
		// prerequisites
		// - escape all irrelevant submissions
		if ( !isset( $_POST['wp-sendmail'] ) ) return;
		// - accept submissions from the same domain only
		if( !isset($_SERVER['HTTP_REFERER'] )) return;
		$referer = parse_url( $_SERVER['HTTP_REFERER'] );
		if( $referer['host'] !== $_SERVER["HTTP_HOST"] ) return; // assume HTTP_HOST?
		// variables
		$data = array();
		// - gather fields
		// * fields from session vars
		foreach($_SESSION as $k => $v ){
			// skip flag
			if( strpos($k, "sendmail_") !== 0 ) continue;
			$field = str_replace("sendmail_", "", $k);
			// special case (legacy name?)
			if( $field == "recipient" ) $field = "to";
			// add key
			$data[$field] = $v;
			// reset variable
			unset( $_SESSION[$k] );
		}
		// * fields with plugin prefix
		foreach($_POST as $k => $v ){
			// skip flag
			if( $k == "wp-sendmail" ) continue;
			if( strpos($k, "wp-sendmail") !== 0 ) continue;
			$field = str_replace("wp-sendmail-", "", $k);
			// skip existing keys
			if( array_key_exists( $field, $data) ) continue;
			// add key
			$data[$field] = $v;
		}
		// * regular POST fields (selective...)
		if( array_key_exists('from', $_POST) && !array_key_exists('from', $data) )
			$data['from'] = $_POST['from'];
		if( array_key_exists('to', $_POST) && !array_key_exists('to', $data) )
			$data['to'] = $_POST['to'];
		if( array_key_exists('subject', $_POST) && !array_key_exists('subject', $data) )
			$data['subject'] = $_POST['subject'];
		if( array_key_exists('message', $_POST) && !array_key_exists('message', $data) )
			$data['message'] = $_POST['message'];

		// - require main fields
		if( empty($data["to"]) || empty($data["from"]) || empty($data["subject"]) || empty($data["message"]) ) return; // return $this->error('empty_fields');

		// optional fields
		if( array_key_exists('fullname', $_POST) ) $data['fullname'] = sanitize_text_field( $_POST["fullname"] );

		// sanitize form values
		$data['from'] = sanitize_email( $data['from'] );
		$data['to'] = sanitize_email( $data['to'] );
		$data["subject"] = sanitize_text_field( $data["subject"] );
		$data["message"] = esc_textarea( $data["message"] );

		// send email
		$this->send( $data );
	}

	// redirect the user if needed
	protected function send( $data ) {
		// error check?
		$from = ( isset($data['fullname']) )
			? "From: ". $data['fullname'] ." <". $data['from'] .">"
			: "From:". $data['from'];
		// headers
		$headers   = array();
		$headers[] = $from;
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-type: text/plain; charset=iso-8859-1";

		// lookup in theme folder (for an override)
		$view = get_template_directory() ."/views/sendmail-message.php";
		if( !file_exists( $view ) ){
			$view = plugin_dir_path( __FILE__ ) ."views/message.php"; // assume it exists?
		}
		// render message from view
		ob_start();
		extract($data, EXTR_SKIP);
		include( $view );
		$data['message'] =  ob_get_clean();

		$submit = mail( $data['to'], $data['subject'], $data['message'], implode("\r\n", $headers) );

		if( $submit == true ) {
			$GLOBALS['wp_sendmail_result'] = "Message sent successfully...";
		} else {
			$GLOBALS['wp_sendmail_result'] = "Message could not be sent...";
		}
		// log submission
		if( $submit == true ) {
			$log = array(
				'recipient' => $data['to']
			);
			if ( isset( $_POST['wp-sendmail-meta'] ) ) $log['meta'] = sanitize_text_field($_POST['wp-sendmail-meta']);
			$this->log( $log );
		}

	}

	function session_start() {
		if(!session_id()) {
			session_start();
		}
	}

	function setup() {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->db;
		$schema = array(
				'id' => "id mediumint(9) NOT NULL AUTO_INCREMENT",
				'recipient' => "recipient tinytext NOT NULL",
				'timestamp' => "timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
				'session' => "session tinytext NOT NULL",
				'meta' => "meta text NOT NULL"
		);
		// first check if table exists
		$table_exists = $wpdb->query("SHOW TABLES LIKE '$table_name'");

		if( $table_exists ){
			// check if all columns are available
			$colunms = array('id', 'timestamp', 'recipient', 'session', 'meta');
			foreach( $colunms as $column ){
				$column_exists = $wpdb->query("SHOW COLUMNS FROM `$table_name` LIKE '$column'");
				if( $column_exists ) continue;
				// create the missing column
				$wpdb->query("ALTER TABLE `$table_name` ADD $schema[$column]");
			}
			// we're done - upgrade option
			update_option('wp_sendmail', $this->version );
		} else {

			$charset_collate = $wpdb->get_charset_collate();
			// check if table exists first?
			$sql = "CREATE TABLE $table_name (".
				$schema['id'] .", ".
				$schema['recipient'] .", ".
				$schema['timestamp'] .", ".
				$schema['session'] .", ".
				$schema['meta'] .", ".
				"UNIQUE KEY id (id)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			add_option( 'wp_sendmail', $this->version );
		}
	}
}

?>
