<?php
	/*
	 * generates a list of Mimetypes, from the latest and greatest Apache repository
	 * - filterd down to stuff applicable to dirList (either a matching <ext>.png icon or
	 *   extension mapping must exist)
	 *
	 * you'll need to manually copy browser output into inc/mimeTypes.inc.php
	 */

	// ensure, we are outputting UTF-8
	header('Content-Type: text/html; charset=utf-8');

	require_once '../inc/common.inc.php';
	require_once '../inc/DirList.class.php';

	define('APACHE_MIME_TYPES_URL','http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types');

	function generateUpToDateMimeArray($url){

		$ROOT = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' );
		$iconPath = $ROOT . '/static/icons/32/';

		// thankfully taken core portions from Josh Sean in user comments on
		// http://www.php.net/manual/en/function.mime-content-type.php
		$s=array();
		foreach(@explode("\n",@file_get_contents($url))as $x)
			if(isset($x[0])&&$x[0]!=='#'&&preg_match_all('#([^\s]+)#',$x,$out)&&isset($out[1])&&($c=count($out[1]))>1)
				for($i=1;$i<$c;$i++) {
					// echo "ONE  ".$out[1][$i]."   TWO".$out[1][0]."<br/>";
					$ext = $out[1][$i];
					if (    !isset( DirList::$mappings[$ext] )
						 && !file_exists($iconPath.$ext.'.png')	)
						continue;

					$s[]='&nbsp;&nbsp;&nbsp;\''.$out[1][$i].'\' => \''.$out[1][0].'\'';
				}

		return @sort($s)?'$mime_types = array(<br />'.implode($s,',<br />').'<br />);':false;
	}

	echo generateUpToDateMimeArray(APACHE_MIME_TYPES_URL);

?>
