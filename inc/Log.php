<?php

/**
 * a simple but effective logging class with a static(!) configuration object
 * and all-static methods for easier logging
 *
 * COULDDO:
 * - dedicated Log::channel (aka a channel info(), a simple solution, since no channel
 *   filtering is reasonable for warn or error anyway )
 *   (and in config: true = ALL channels, false = NO channels, array = SOME channels)
 *   if no channel is specified ( for info ) the channel will be the class basename
 *
 *
 * - decoration (data, file, line (by way of backtrace, ...)
 * - implicit var_dump (if $msg is not a primitive value)
 *    - always give type for anything but string
 *    - indented dump for object, array
 *    - don't be oververbose on showTime, showFile => direct recursion not an option
 *
 * usage example:
 *
 *     Log::config(
 *	      Log::SCREEN,
 *	      'log/foo.txt' // relative to webroot, unless $ROOT provided
 *     );
 *
 * @author franknocke@gmail.com
 */
class Log {

	const LEVEL_INFO  = 1; // info() aka trace() aka log()
	const LEVEL_WARN  = 2;
	const LEVEL_ERROR = 3; // do not call directly, should be called by fail() and enforce()

	// 'magic constants for output'
	const SCREEN  = '_SCREEN_'; // aka echo()
	const LOG     = '_ERRLOG_'; // aka error_log()

	// actual configuration
	private static $config;

	// default configuration
	private static $defaultConfig = array(
		'ROOT'      => false,
		'header'    => '------------------',
		'loglevel'  => self::LEVEL_INFO,     // by default, show all
		'channels'  => true, // by default, show all channels
		'html'      => true, // html-encode screen output? (if any. Likely in pages, unlikely in script output)
		'targets'   => array(
			// by default logs to the system log (error_log()) only (as you'd expect)
			self::LOG,
		),
		// TODOs
		'showTime' => true, // 2012-06-08 15:45: Hans was here
		'showFile' => true, // Banana.php:16: Hans was here
	);


	/*
	 * PRIVATE functions --------------------------------------------------------
	 */

	/**
	 * turn anything into a readable string
	 */
	private static function serialize( $o )
	{
		if ( is_string($o))
			return $o;

		return print_r( $o, true );
	}


	// COULDDO:something with funcNumArgs to allow more paremters (at least a 2nd descriptive message or so)
	// http://stackoverflow.com/questions/828709/php-get-all-arguments-as-array
	
	private static function out($msg)
	{
		$msg = self::serialize($msg);

		$c =& self::$config; // shorthand

		foreach( $c->targets as $t )
			switch( $t ) {
				case self::SCREEN:
					if ($c->html)
						echo htmlentities( $msg )."<br/>\n";
					else
						echo $msg."\n";
					break;
				case self::LOG:
					error_log( $msg );
					break;
				default: //assume file output
					error_log( $msg."\n", 3, $c->ROOT.$t);
					break;
			}
	}

	/*
	 * PUBLIC functions --------------------------------------------------------
	 */

	/** one initial call of config() is required
	 * (without parameters for default Params, done below at bottom of this file)
	 */
	public static function config(array $config = array())
	{
		// merge in (new)config settings
		self::$config = (object) array_merge( self::$defaultConfig, $config);
		$c =& self::$config; // shorthand

		if ($c->ROOT === false)
			$c->ROOT = $_SERVER['DOCUMENT_ROOT'];
		$c->ROOT = rtrim($c->ROOT,'/').'/'; // normalize

		if($c->header !== false)  // prints -------------- (to structure logs. and a require-once check if you will)
			self::out($c->header);
	}

	public static function info($msg='') {
		if (self::$config->loglevel <= self::LEVEL_INFO )
			self::out($msg);
	}

	public static function warn($msg='') {
		if (self::$config->loglevel <= self::LEVEL_WARN )
			self::out($msg);
	}

	public static function error($msg='') {
		self::out($msg);
	}

	//TODO:
	// public static function channel($msg,$ch) {
	//   ...
	// }

	public static function clearLogs() {
		$c =& self::$config; // shorthand
		foreach( $c->targets as $t )
			switch( $t ) {
				case self::SCREEN:
					//nothing
					break;
				case self::LOG:
					$t = ini_get('error_log');
					if ( !$t ) // null or false
						break;
					// (sic) otherwise keep going
				default: //assume file output
					$log = fopen( $t, "w");
					fwrite($log, "trace.log cleared.\n");
					fclose($log);
					break;
			}	
	} // clearLogs()

} // class

// set default config. (Can be overriden time and again.)
Log::config();

?>
