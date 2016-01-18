<?php
	// error reporting (be lenient about PHP versions)
	error_reporting( defined('E_STRICT') ? ( E_ALL | E_STRICT ) : ( E_ALL ) );

	$ROOT = rtrim($_SERVER['DOCUMENT_ROOT'], '/'); // slashless (sanitize, because it exists in some environments)
	$HOST = $_SERVER['HTTP_HOST'];                 // slashless (obviously)

	// require_once 'config.inc.php';

	$GLOBALS['isLocalHost'] =
			( 0 == strncmp('localhost', $HOST, 9) )   ||
			// add more vhosts here, if the need arises:
			( 0 == strncmp('local.', $HOST, 6) );

	$GLOBALS['isDevMode'] = $GLOBALS['isLocalHost']; // COULDO: || <staging server1> || ...

	require_once('Log.php');

	if ( in_array(('clearLog'), $_GET) )
			Log::clearLogs();

	function enforce($statement, $msg='(no message)') {
		if (!$statement)
			throw new RuntimeException( "enforce failed: $msg" );
	}

	function fail($msg='') {
		throw new RuntimeException( "failed: $msg" );
	}

	function test($statement, $msg='') {
		enforce($statement, "FAILED test: $msg");
		out("PASSED test '$msg'");
	}

	function testCmp($v1, $v2, $msg='') {
		enforce( $v1 === $v2, "FAILED testCmp: msg: $v1 (".gettype($v1).") does not equal $v2 (".gettype($v2).")" );
		out("PASSED test '$msg'");
	}


	function validate($s, $pattern, $msg = '') {
		enforce(1 === preg_match($pattern, $s), $msg);
	}

	/**
	 * The clou being, that pre and post are not output,
	 * if array is empty.
	 *
	 * Handy for
	 * - putting together sql queries.
	 * - avoiding moot <div "<?=implode(' ',$divClasses)?> with nothing in there...
	 *
	 * @param type $pre
	 * @param type $arr
	 * @param type $post
	 * @param type $glue
	 * @param type $trail
	 * @return type
	 */
	function implodeX( $pre, $arr, $post='', $glue = ', ' ) {
		enforce( is_array($arr) );
		if ( empty($arr) )
			return '';
		return $pre.implode($glue,$arr).$post;
	}

	function dumpQuery($query) {
		$result = mysql_query($query);

		$num_rows = mysql_num_rows($result);
		for ($i = 0; $i < $num_rows; $i++) {
			$row = mysql_fetch_array($result, MYSQL_ASSOC);
			if ($i == 0)
				foreach ($row as $key => $value)
					echo ("<b>$key, </b>");
			echo("<br>\n");
			foreach ($row as $key => $value)
				echo ("$value, ");
		}
	}

	/**
	 * return true if needle is not contained in haystack.
	 * @param $needle may be an array. In that case, returns false only if ALL needles cannot be found
	 */
	function contains($haystack, $needle, $insensitive = true) {
		if ( is_array($needle) )
		{
			foreach( $needle as $n )
			{
				if ( contains($haystack, $n, $insensitive) )
					return true;
			}
			return false;
		}

		if ($insensitive)
			return ( false !== stristr($haystack, $needle) );
		else
			return ( false !== strstr($haystack, $needle) );
	}


	/* turns a normal utf-8 text into something url-"rambleable"
	 * Die Brücke von A & B => die-Bruecke-von-A-B
	 * ALSO CONSIDER disarm() for stuff from client-side !
	 */
	function slug($str) {
		$str = str_replace(
				array('ö', 'ü', 'ä', 'ß', '@'), array('oe', 'ue', 'ae', 'sz', ' at '), $str
		);
		$str = (trim($str));
		$str = preg_replace('/[^A-Za-z0-9-%]/', '-', $str);
		$str = preg_replace('/-+/', "-", $str);
		return $str;
	}

	/*
	 * replaces \' \" leftovers after json decode with actual ' "
	 */
	function json_clean($str)
	{
		$r = ensureUTF8( $str );
		$r = str_replace( array('\\"', '\\\''), array('"', "'"), $r);
		$r = htmlentities( $r, ENT_QUOTES, 'UTF-8' );
		return $r;
	}

	function hexDump($str) {
		return implode(' ',
					array_map( 'dechex',
							   array_map('ord', str_split( $str )))
				);
	}

	// nice no-brain, relatively failsafe...
	// converts to UTF-8 *if* ISO-8859-1 detected (as may happen in search queries, URL portions, etc...)
	// returns converted string, unchanged string if $str is empty or not valid ISO-8859-1
	// ( btw: strict ASCII strings (0-127, no 'high ASCII') are valid ISO and valid UTF-8 at the same time,
	//   so it doesn't matter at all )
	function ensureUTF8( $str ) {
		if ( (strlen($str) > 0) &&
			 'ISO-8859-1' == mb_detect_encoding( $str, 'UTF-8, ISO-8859-1', true))
			return utf8_encode( $str );
		else
			return $str;
	}

	/**
	 * allways return the modulo as a positive [ 0,%mod [
	 */
	function posmod($v, $mod) {
	  return ($mod + ($v % $mod)) % $mod;
	}

	/*
	 * disarms customers trings like addresses, emails of anything html/sql injection-ish
	 * (as an additional safetly measure. uses somewhat close replacements to maintain readibilty)
	 * NOTE: replace chr(10) by nothing, otherwise additional spaces come in, in particular in multi-line text fields)
	 */

	function disarm($str) {
		return str_replace(
						array('<', '>', ';', ':', '\'', '"', '|', '{', '}', '\\', '´', '`', chr(0), chr(10)), array('', '', '.', '.', ' ', ' ', 'l', '(', ')', '/', '', '', '', ''), $str
		);
	}


	/**
	 * detects most common mobilde devices (and tablets, no difference
	 *
	 * REF: http://html5-mobile.de/blog/wichtigsten-user-agents-mobile-devices-jquery-mobile
	 *
	 * @param type $userAgent pass in the $userAgent - otherwise simply grabs it from $_SERVER itself
	 * @param type $device no input value, returns device (CBR) if wanted
	 * @return type
	 */
	function isMobile( $userAgent = false, &$device = false ) {
		if( $userAgent === false )
			$userAgent = $_SERVER['HTTP_USER_AGENT'];

		$MOBILE_PHONES = '/(iPad|iPod|iPhone|Android.*Mobile|Android|BlackBerry|PlayBook|Kindle|Opera Mobi|Windows Phone)/i';
		// TODO: deal with Android Mobile vs. normal

		$matches = false;
		$r = preg_match( $MOBILE_PHONES, $userAgent, $matches);
		$device = (count($matches)>1) ? $matches[1] : '';
		return $r > 0;
	}


	/* great for deciding on loading minified js files,
	 * if thumbs are sufficently recent and other lazy stuff
	 *
	 * nb: a single newer piece of hay means: is newer
	 */
	function isNewer( $needle, $haystack ) {

		if ( !file_exists($needle) ) return false; // if non-exist, anything is newer...
		$ntime = filemtime($needle);
		$files = false;

		enforce( is_string( $haystack ) || is_array( $haystack ), 'must be string or array');
		if ( is_array( $haystack ))
			$files = $haystack;
		else
			$files = array( $haystack );

		// now we do have in array in every case
		foreach ( $files as $file )
		{
			if ( !file_exists($file) || filemtime($file) > $ntime )
				return false;
		}
		return true;
	}

	/*
	 * urlencode all listed fields
	 */
	function encodeX( &$arr, $blacklist=array() )
	{
		foreach( $arr as $k => $v )
		{
			if ( !in_array($k,$blacklist) )
				if( is_array($arr) )
					$arr[$k] = htmlspecialchars( $v, ENT_QUOTES, 'UTF-8');
				else
					$arr->$k = htmlspecialchars( $v, ENT_QUOTES, 'UTF-8');
		}
	}

	/*
	  a good shorthand for those every checked mysql queries:
	  - throws rather than trigger_error() to allow recovery
	  in try-catch clauses (i.e. for AJAX/CRUD requests)
	 */

	function checked_mysql_query($sql, $out = false ) {
		if ( $out )
			out( "query: $sql");
		$result = mysql_query($sql);
		if ( $result === false )
				fail("mysql query failed:\n" . mysql_error() . ", \nquery was:" . $sql);
		return $result;
	}

	function warn($msg = 'empty warning') {
		echo "<h3 class='warn'>warning: $msg</h3><br/>\n";
	}


	// indented output functions ----------------------------------------------

	/**
	 * @param integer $inc use values >1 to enforce a certain indendation to start
	 */
	function out($msg='', $inc=0, $trace = false) {
		if( is_array($msg) ) // convenience
			return outPrint($msg);
		if ( is_bool($msg) )
			$msg = ($msg) ? 'true' : 'false';

		static $tab = 0; // default indentation
		static $LF = "\n";
		static $tabs = array( //
		"", "\t", "\t\t", "\t\t\t", "\t\t\t\t", "\t\t\t\t\t", "\t\t\t\t\t\t", //
		"\t\t\t\t\t\t\t", "\t\t\t\t\t\t\t\t", "\t\t\t\t\t\t\t\t\t" );

		if ($inc === -1) $tab--;
		enforce( $tab >= 0, 'too many outPop()');
		if ($inc > 1 ) $tab = $inc; // set indent explicitly

		$oLog = $oScreen= $tabs[$tab] . $msg . ((outWithBR()) ? "\n" : '');

		// in BR (aka html) mode spaces => &nbsp;, \n (inline ones!) => <br/>
		if ( outWithBR() )
			$oScreen = trim( nl2br(str_replace (' ', '&nbsp;', $oScreen)));

		if ( !$trace && outToScreen())
			echo $oScreen.$LF;
		if ( $trace || outToLog())
			error_log(trim($oLog));

		if ($inc >= 1) $tab++;

		// intentionally not getting tab out-of-bounds errors,
		// performance for one, telltale sign that something is functionally wrong
		// for another!
	}

	function outPush($msg, $inc=1 ) {
		out($msg, $inc);
	}

	function outPop($msg = '') {
		out($msg, -1);
	}

	function outEval($msg) {
		eval( "global $msg;");
		$e = "out('$msg: '.  ((!is_bool($msg))?($msg):(($msg)?'true':'false'))  );";
		eval( $e );
	}


	function trace( $msg ) {
		out( $msg, 0, true );
	}

	/*
	 * print_r's as you'd expected. Except with correct out-indentation
	 */
	function outPrint( $v, $label='' ) {
		outPush( (empty($label)? '' : "$label:" ) );
		{
			$r = print_r($v,true); // strip_tags(var_dump($v));
			$r = explode("\n",trim($r));
			foreach( $r as $line )
			{
				out($line);
			}
		}
		outPop();
	}

	// output with or without BR ? - state machine
	// @param bool toggle set to true or false.
	//             null resp. no param to use as getter (jQuery style)
	function outWithBR( $toggle=null ){
		static $state=false; // static !
		if ( $toggle===null ) return $state; // internal state getter
		$state = $toggle;
	}

	// output to error log, too? - state machine. see above
	function outToLog( $toggle=null ){
		static $state=false; // static !
		if ( $toggle===null ) return $state; // internal state getter
		$state = $toggle;
	}

	// output to screen. Disabled only for live errors.
	function outToScreen( $toggle=null ){
		static $state=true; // static !
		if ( $toggle===null ) return $state; // internal state getter
		$state = $toggle;
	}


	// convert kBytes between "ini-lingo" (T,G,M,K), "human" (TB,GB,MB,kb) and plain bytes
	function ini_to_byte($s)
	{
		if ( is_numeric($s) && is_int(0+$s) ) // plain number?
			return 0+$s;

		$l = substr($s, -1);
		$ret = 0+substr($s, 0, -1);
		enforce( is_int($ret) );
		switch(strtolower($l)){
		case 'p':
			$ret *= 1024;
		case 't':
			$ret *= 1024;
		case 'g':
			$ret *= 1024;
		case 'm':
			$ret *= 1024;
		case 'k':
			$ret *= 1024;
		case 'b':
			// nothing
			break;
		}
		return $ret;
	}

	function byte_to_human( $b, $precision=1 )
	{
		$b = 0+$b; // convert to int, if needed
		enforce( is_long($b), '$b is not an int' );

		if ( $b < 1024 )
			return $b.' byte';
		if ( $b < 0.9*1048576 ) // 0.9 round higher levels if close enough
			return round( $b/1024, $precision) .' kb';
		if ( $b < 0.9*1073741824 )
			return round ( $b/1048576, $precision) .' MB';
		if ( $b < 0.9*1099511627776 )
			return round ( $b/1073741824, $precision) .' GB';
	}

	// error handling and logging --------------------------------------------------

	function grabSourceLine( $faultLine ) // was: getCulpritInfo
    {
		if ( ! isset( $faultLine['file'] ))
			return ( 'can not log error, empty faultline[file]' );
		if ( ! isset( $faultLine['line'] ))
			return ( 'can not log error, empty faultline[line]' );

        $lines = file( $faultLine['file'] ); // could be heavily optimzed ("flygate")
        $culprit = $lines[ $faultLine['line'] - 1 ]; // line counting differs by 1
        return  $faultLine['file'].':'.$faultLine['line']."\n--> ".trim( $culprit );
    }

	// receives the data from errors/exceptions respectively
	function userJointHandler( $errno, $errmsg, $filename, $linenum, $callStack, $errorType, $survive = false )
	{
		restore_error_handler(); // prevent recursion in error handling !
		outToLog(true);  // enable error logging ( localhost, or not)
		outWithBR(true); // enable <br>, just in case
		out();

		// remove any non-file-associated handler notice from stack (to grab info on actual culprit)
		if ( ! isset($callStack[0]['file']) )
			array_shift( $callStack );

		// remove any exception re-throw from stack (to grab info on actual culprit)
		if ( 0 == strcmp ( $callStack[0]['file'] , __FILE__))
			array_shift( $callStack );

		$sourceLine = grabSourceLine( $callStack[0] );

		// only be talkative on localhost or if (traceLive) explicitly wanted
		// ! survive means: be mute on silent errors
		outToScreen( $GLOBALS['isLocalHost'] && ! $survive );

		$aCallstack= array_reverse( $callStack );
		foreach($aCallstack as $aCall)
		{
			if (!isset($aCall['file'])) $aCall['file'] = '';
			if (!isset($aCall['line'])) $aCall['line'] = '';

			// FRANK TEMP BACKUP
			error_log("{$aCall["file"]}:{$aCall["line"]}&nbsp;&nbsp;&nbsp;&nbsp;{$aCall["function"]}(...)");

			out("{$aCall["file"]}:{$aCall["line"]}&nbsp;&nbsp;&nbsp;&nbsp;{$aCall["function"]}(...)");
		}
		out( $sourceLine );
		out( '----------------------------' );

		return false; // TEMPTEMP avoid running PHP's internal error handler
	} // userJointHandler

	/*
	 * passing in true allows to send a silent exception to log while keep parsing
	 */
	function userExceptionHandler( $e, $survive = false )
	{
		$trace = $e->getTrace();
		// add place of exception to trace:
		array_unshift( $trace, array ( "file"=>$e->getFile(), "line"=> $e->getLine(), "function" => 'Exception thrown' ) );
		userJointHandler( $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), $trace, 'exception', $survive );
	}

	function userErrorHandler($errno, $errmsg, $filename, $linenum, $var )  //(sic) $var unused
	{
		$trace = debug_backtrace();
		userJointHandler( $errno, $errmsg, $filename, $linenum, $trace, 'error' );
	}

	//enable:
//	if( $GLOBALS['isDevMode'] )
//	{
//		set_exception_handler("userExceptionHandler");
//		set_error_handler("userErrorHandler", E_ALL );
//	}

?>
