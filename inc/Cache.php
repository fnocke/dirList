<?php

require_once 'common.inc.php';
enforce( extension_loaded('gd') || extension_loaded('gd2'), 'no gd lib found' );

/**
 * simply rethrows to make it catchable (and mute)
 * - needed during metadata extraction
 */
function cache_error_handler($no,$str,$file,$line) {
	throw new ErrorException($str,$no,0,$file,$line);
}

class Cache {

	// actual configuration
	private static $config = false;

	// default configuration
	private static $defaultConfig = array(
		'JPEG_QUALITY'		 =>	85,

		// you MUST set these ( trailing slash )
		// directory path to the cache folder (i.e. to grab those timestamp)
		// url to the where to request (could be '/cache' or e.g. http://static.foo.com/...')
		'CACHEPATH'      => '/_cache/',		// actual physical path rel. to document root
		'CACHEURL'       => '/cache/',		// root-relative or absolute web access to it

		// what icon to use on failed thumbnailing?
		'BROKENURL'     => '/static/icon/broken.png',

		/* limit permitted thumb request sizes, to avoid 'bloat attacks' by asking
		 * for arbitrary sizes or also sneaky fullRes downloads */
		'PERMITTED_SIZES'	 =>	array( 32, 48, 64, 96, 100, 128, 134, 150, 192, 200, 256, 300, 384, 400, 500, 800, 1280 /* for slideshows */ ),
		'PERMITTED_SCALINGS' =>	array( 1.0, 1.5, 1.8, 2.0 )
	);


	/**
	 * one initial call of config() is required. (empty parameters for default settings is fine)
	 * you will either get a failed enforce or "Trying to get property of non-object"
	 */
	public static function config(array $config = array())
	{
		// merge given (precedence) and default config
		self::$config = (object) array_merge( self::$defaultConfig, $config);
		$c =& self::$config; // shorthand

		$c->ROOT = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ); // normalize trailing
		$c->CACHEPATH = $c->ROOT.'/'.$c->CACHEPATH;
		enforce( is_dir($c->CACHEPATH), "invalid cache directory $c->CACHEPATH" );
	}

	public static function getScaledBox($mode, $srcW, $srcH, $sizeX, $sizeY) {

			$srcRatio = $srcW / $srcH;
			$dstRatio = $sizeX / $sizeY;

			switch( $mode )
			{
				case 'b': // boxing in
					if ( $srcRatio > $dstRatio )
					{  // source is wider -> x rules
						$dstW = $sizeX;
						$dstH = floor( $sizeX / $srcRatio );
					}
					else
					{  // source is taller ->
						$dstH = $sizeY;
						$dstW = floor( $sizeY * $srcRatio );
					}
					break;
				default:
					fail("invalid boxing mode $mode");
					break;
			}

			return array( $dstW, $dstH );

	}

	/**
	 * verifies existence of a thumb in a particular resolution
	 * returns the url, false if there is no source image!
	 * - smart "semi-static" caching including thumbnail timestamp in url
	 *   ==> be sure to understand the rewrite rules
	 *   ==> be sure to have a proper cache/ to _cache/ rewrite in your root .htaccess
	 *
	 * @param string $file   - path to original file
	 * @param string $mode   - scale mode. currently only 'b' (boxing in) supported
	 * @param int $sizeX  - target width (must be within $c->PERMITTED_SIZES)
	 * @param int $sizeY  - target height (must be within $c->PERMITTED_SIZES)
	 * @param bool $force  - ignore cache and always reproduce (maybe during development)
	 * @param bool $timestamp - attach timestamp to url (and remove leading underscore?)
	 * @param int $dstW   - CBR - passes back resulting size (<=$sizeX)
	 * @param int $dstH   - CBR -    "   , important for alignment, especially vertical
	 * @param float $scale - deliver Thumb bigger by this factor. dstW, dstH remain unaffected. (used to prep for CSS3 scalings)
	 *
	 * @return string the url (which to use in the img tag, gets rewritten to actual path)
	 */
	public static function getThumb( $file, $mode, $sizeX, $sizeY, $force=false, $timestamp=true, $scale=1.0, &$dstW=false, &$dstH=false ) {

		$c =& self::$config; // shorthand
		enforce( self::$config !==false, 'you neet to call config() first');

		//convert int and save pre-scae
		$origSizeX = $sizeX = 0+$sizeX;
		$origSizeY = $sizeY = 0+$sizeY;

		enforce( is_int($sizeX), "sizeX is not an int");
		enforce( is_int($sizeY), "sizeX is not an int");
		enforce( in_array($sizeX, $c->PERMITTED_SIZES,true), 'not a permitted sizeX');
		enforce( in_array($sizeY, $c->PERMITTED_SIZES,true), 'not a permitted sizeY');
		enforce( in_array($scale, $c->PERMITTED_SCALINGS,true), 'scale not allowed');

		if ($scale !== 1.0)
		{
			$sizeX = intval(round($sizeX*$scale));
			$sizeY = intval(round($sizeY*$scale));
		}

		// for the moment only boxed mode is supported...
		enforce( in_array($mode, array('b'),true), 'invalid dimension (read: not boxed mode)' );
		$srcUrl = $c->ROOT.'/'.$file;

		/*
		 * fallback image in case thumb creation fails (likely cases: broken files, and still in upload)
		 * benefit of this approach: if upload continues and eventuell gets final, it does have a newer
		 * timestamp than what got created from '$broken'... ==> subsequent reloads have a chance.
		 */

		if( !file_exists( $srcUrl ) )
		{
			out("file $srcUrl does not exist");
			$dstW = $dstH = 0;
			return false; // (also important, to not show thumb, if orig gone...)
		}

		// computing dstUrl, must also take local path to source into account etc... -----------------------------
		// -arguably- obfuscation might be desirable, although this stands against SEO
		//
		// REF:
		// dstUrl: Dörte A一B二CÖDE _cache/c7_u/bearbeitet-7_upload_b300-226.jpg
		// dstUrl: D__rte_A___B___C__DE__cache/c7_u/bearbeitet-7_upload_b300-226.jpg

		$parts[0] = str_replace(
			array( 'ö', 'ü', 'ä', 'ß', '@' ),
			array( 'oe', 'ue', 'ae', 'ss', ' at ' ),
			strtolower( $file )
		);
		$parts[0] = trim( str_replace( array('/','\\'), '-', $parts[0]), ' -');
		$parts[0] = preg_replace( '/([^\w\/\-\.])/','_', $parts[0] );

		$parts[] = $mode;
		$parts[] = $sizeX;
		$parts[] = $sizeY;

		$dstUrl = implode('-',$parts);

		// spread out into subDirs: safer/better performance
		// avoids limitations with certain file systems (too many files in a single dir)
		$subDir = preg_replace( '/([^\w\d])/','', $dstUrl );

		// hint:bump letter if something basic about thumbnailing changes
		// (as a hack to invalidate all prior thumbs, also on live)
		$subDir = 'd'.substr( $subDir, 0, 3 );
		$subDirPath = $c->CACHEPATH.$subDir; // for mkdir, below

		// turn into absolute path, and add thumb extension
		$dstCore = $subDir.'/'.$dstUrl.'.jpg';
		$dstUrl = $c->CACHEURL.$dstCore;
		$dstPath = $c->CACHEPATH.$dstCore;


		if ( $force || !isNewer($dstPath, $srcUrl) ) // (re-)build thumb?
		{
			set_error_handler( 'cache_error_handler' );
			{
				try{
					// just peeking for errors
					getimagesize($srcUrl);

					$srcImg = false;

					switch( strtolower(pathinfo($srcUrl,PATHINFO_EXTENSION)) )
					{
						case 'jpg':
						case 'jpeg':
							$srcImg = imagecreatefromjpeg( $srcUrl );
							break;
						case 'png':
							$srcImg = imagecreatefrompng( $srcUrl );
							break;
						default:
							fail('unknown extension for thumbnailing');
					}
				}
				catch( Exception $e)
				{
					enforce( is_file($c->BROKENURL), 'image for failed thumbs does not exist: '.$c->BROKENURL);
					$srcUrl = $c->BROKENURL;
					$srcImg = imagecreatefrompng( $srcUrl );
					$fallbackImage = true;
				}
			}
			restore_error_handler();

			if ( ! file_exists( $subDirPath ))
			{
				enforce( mkdir( $subDirPath, 0777, true ));
			}
			enforce( file_exists( $subDirPath ) );

			$srcW = $srcH = $dstW = $dstH = $image_type = false;
			list( $srcW, $srcH, $image_type) = getimagesize($srcUrl);
			enforce( $srcW > 0 && $srcH > 0, 'sanity');

			list( $dstW, $dstH ) = self::getScaledBox($mode, $srcW, $srcH, $sizeX, $sizeY);

			$dstImg = imagecreatetruecolor( $dstW, $dstH );
			enforce( imagecopyresampled( $dstImg, $srcImg, 0,0 , 0,0 , $dstW, $dstH, $srcW, $srcH ),'resizing failed');

			// Output and free memory
			imagejpeg( $dstImg, $dstPath, $c->JPEG_QUALITY );
			chmod( $dstPath , 0666 ); // do not enforce

			imagedestroy( $srcImg );
			imagedestroy( $dstImg );

			// recompute unscaled values, if needed
			if ($scale !== 1.0)
			{
				list( $dstW, $dstH ) = self::getScaledBox($mode, $srcW, $srcH, $origSizeX, $origSizeY);
			}

		}
		else // if old, still must obtain thumb size:
		{
			list( $dstW, $dstH ) = getimagesize($dstPath);
			// COULDDO: take from a to-be meta-sidecar

			// this is akward.
			$dstW = intval(round($dstW / $scale));
			$dstH = intval(round($dstH / $scale));
		}

		if ( $timestamp )
		{	// also remove leading underscores (those would trigger a Rewrite)
			$dstUrl = ltrim($dstUrl, '_').'/'.filemtime($dstPath);
		}

		return $dstUrl;
	} // ensureThumb
} // class

// no default call to ::config , e.g. because initial availability asserts in constructor could fail.
