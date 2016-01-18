<?php

/**
 *
 * see the higher-level readme.txt
 *
 * no warranty implied or expressed
 *
 */
require_once 'common.inc.php';

require_once('mimeTypes.inc.php');
require_once('Log.php');
require_once('Meta.php');

class DirList {

	static $tabs = array( //
		"", "\t", "\t\t", "\t\t\t", "\t\t\t\t", "\t\t\t\t\t", "\t\t\t\t\t\t", //
		"\t\t\t\t\t\t\t", "\t\t\t\t\t\t\t\t", "\t\t\t\t\t\t\t\t\t" );
	static $defaultConfig = array(
		'url' => null, // null will lead to REQUEST_URI
		'renderMode' => 'auto', // known modes auto (TODO: base on thresholds), '
		/*
		 * auto means:
		 * >80% jpg/png ==> thumb,
		 *		<12 files thumb-big, else thumb-small   ( TODO: Icons in 128px flavours? )
		 * else <8 files list-big, else list-small
		 *
		 * be more conservative on mobile devices:
		 * http://stackoverflow.com/questions/5981730/how-do-i-detect-mobile-clients-on-my-web-server
		 *
		 */

		'PARSE_URLS' => true, // .url Files (Internet Explorer Shortcuts/Windows Links) will result in external links
		'SHOWPARENT' => true, // show now ..
		'SHOWCREDITS' => true, // TODO appear as part of render()

		// TODO fields (default und per view) to show.
		//     - dependency on view?
		//     - dependency on object type? ( for JPEG certainly metadata, for most else filename )
		//				-  proper caching based on timestamp? simple (de-serializer) ?

		'ICONPATH' => '/somewhere/static1234/icons/',
		'ICONURL'  => '/static/icons/',

		'ICON_EXT'     => 'png',  //pls be case-sensitive!
		'ICON_DEFAULT' => 'default.png',
		'ICON_PARENT'  => 'up.png',
		'ICON_FOLDER'  => 'folder.png',

		// > 80% thumb-able images ==> 'auto' mode means 'grid', less means 'list'
		'GRID_THRESHOLD' => 0.4,

		'iconWidth' => 32,  // TODO: remove, will depend on renderMode ( 32 -> 64 -> 128 -> 256 )
		'iconHeight' => 32,
		'thumbScale' => 1.5, // render thumbs biger than <img>-Dimensions (to enlarge using css3 transitions), should match css
		'indent' => 4, // initial indent for readme and render() [0..10]

		'README' => '^readme.txt$', //the file(s) that will be outputted in readme()
		// BOTH must be passed, for listing and for download:
		'BLACKLIST' => array(
			'/^\.ht/i',  // anything .ht*
			'/^(dirlist|helpers|static|_static|cache|_cache)/i', // dirlist folders (match start)
			'/^(admin|inc|protected|private)$/i', // protected folders (match full name)
			'/^(robots.txt)$/i', // specific files (match full name)
			'/(~|passwd|password|htaccess|htpasswd)/i', // anything around 'home' or the most infamous
			'/\.(php|js|inc|class|ini|conf|yml|log|error)$/i'
		),
		// additional stuff not desired to be shown (if you prefer not the wipe the regular blacklist)
		'BLACKLIST_CUSTOM' => array(),
		'WHITELIST' => array(
			'/\.(txt|jpg|rar|jar|zip|doc|pdf|bmp|tif|url|png|cpp|avi|mp4|mkv)$/i'
		)
	);


	// extensions, for which there is thumbnail support (nb: no mapping applies)
	public static $thumb_support = array(
		'jpg' => true,
		'png' => true
	);

	// do we have a mean for reasonable metadata (nb: no mapping applies)
	public static $meta_support = array(
		'jpg' => true,
		'tif' => true
	);

	// map some common icons to save on redundant icons. All Images share one icon.
	// Avoid letter-bearing icons. ( mapping an extension to another icon showing .xyz in the graphic )
	public static $mappings = array(
		'url' => 'lnk', // mapping for links

		'mpg' => 'avi',
		'mp4' => 'avi',
		'mkv' => 'avi',
		'asx' => 'avi',
		'vob' => 'avi',
		'wmv' => 'avi',

		'htm' => 'html',

		'jpeg' => 'jpg',
		'png'  => 'jpg',
		'gif'  => 'jpg',
		'tif'  => 'jpg',
		'tiff' => 'jpg',
		'psd'  => 'jpg',
		'tga'  => 'jpg',
		'bmp'  => 'jpg',
		'gif'  => 'jpg',
		'xpm'  => 'jpg',

		'ogg' => 'mp3',
		'm4a' => 'mp3',
		'mpa' => 'mp3',
		'wma' => 'mp3',

		'make' => 'ini',
		'conf' => 'ini',
		'yml'  => 'ini',
		'yaml' => 'ini',

		'dmg'  => 'iso',

		'bash' => 'sh',
		'csh'  => 'sh',
		'vb'   => 'sh',
		'wsf'  => 'sh',

		'ai'   => 'eps',
		'ps'   => 'eps',
		'svg'  => 'eps',
		'dxf'  => 'eps',

		'cmd'  => 'bat',

		'kml'  => 'gpx',
		'kmz'  => 'gpx',
		'osm'  => 'gpx',
		'nmea'  => 'gpx',

		'docx' => 'doc',
		'xlsx' => 'xls',

		'rar' => 'zip',
		'deb' => 'zip',
		'bz2' => 'zip',
		'rpm' => 'zip',
		'tar' => 'zip',
		'tgz' => 'zip',
		'gz' => 'zip'
	);

	// Constants ------------------------------------------
	// else

	public $config = false;
	public $url = false;

	private $starttime = null;

	public $fileList = array( );

	// files (excluding directories),
	// (thumb-able) images ( used for auto-mode decision )
	// directories,
	// total size in bytes
	private $fileCount = 0, $thumbCount = 0, $dirCount = 0, $totalSize = 0;

	private $readme_content = ''; //aggregated readme contents

	/**
	 * check's if $needle matches at least one expression in $mat5chset,
	 * useful for black- and whitelist checking
	 *
	 * @param type $needle
	 * @param array $matchSet
	 *
	 * @return true, if at least one match found
	 */
	protected static function matches( $needle, array $ruleSet )
	{
		foreach ( $ruleSet as $rule ) {
			if( preg_match($rule, $needle) > 0 )
				return true; // match found
		}
		return false;
	}

	/*
	 * turn plain integer byts into a human readable size ' bytes, kb, MB'
	 */
	protected static function humanSize( $size, $precision = 0 )
	{
		if( $size < 1024 )
			$r = $size.'&nbsp;bytes';
		else if( $size < 1024 * 1024 )
			$r = round($size / 1024, $precision).'&nbsp;kb';
		else if( $size < 1024 * 1024 * 1024 )
			$r = round($size / 1024 / 1024, $precision).'&nbsp;MB';
		else
			$r = round($size / 1024 / 1024 / 1024, $precision).'&nbsp;GB';
		// COULDDO:size and unit in different spans (for alignment styling)
		return $r;
	}

	/**
	 * checks for a download=<filename> GET paramter,
	 * and if found, triggers that download.
	 *
	 * WARNING: parameter-based download functions are a notorious security hole,
	 * trying to download htaccess, htpassword, ini, cfg and other files.
	 * This is why I apply a WHITElisting of explicitly permitted-files,
	 * rule out parent dirs, try to avoid stuff disuised by encoding, ...
	 * Test carefully, use at your own risk. (like everything)
	 *
	 * Must be called before ANY browser output (to all for headers, etc...)
	 */
	public function checkDownload() {
		global $mime_types;

		if ( !isset($_GET['download'] ))
			return;

		$c =& $this->config; // shorthand

		$fileName = $_GET['download'];
		// avoid any file starting with a dot, parent-folderish (in bare filename),
		// any forward or backward slash, ...
		enforce( substr( $fileName, 0, 1 ) != '.', 'no hidden files' );
		enforce( contains($fileName, '.'), 'no extensionless files for download' );
		enforce( !contains($fileName, array( '%2E','%5C','%2F','..','\\','/' )),
				 'download not possible' ); // (disguised) dots,slashes or parents

		$filePath = $this->ROOT.$c->url.'/'.$_GET['download'];
		enforce( self::matches($filePath, $c->WHITELIST), 'no match' );
		enforce( file_exists( $filePath ), 'no such file' );

		// TODO: extract mimetype from filepath (see below)
		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION ));
		enforce( isset( $mime_types[$ext]), 'unknown mimetype' );
		$mime = $mime_types[$ext];
		header('Content-Type: $mime');
		header('Content-Disposition: attachment; filename="'.ensureUTF8($fileName).'"');
		readfile( $filePath );
		exit(); // also to avoid any trailing crud
	}

	private function timeSpent() {
		return microtime(true) - $this->starttime;
	}

	public function __construct( array $config = array() ) {

		// needed for ensure thumbs
		// this is on the assumption, that not very much time was wasted
		// in the header section on other stuff
		$this->starttime = microtime(true);

		// merge given (precedence) and default config
		$this->config = (object) array_merge( self::$defaultConfig, $config );
		$c =& $this->config; // shorthand

		$this->ROOT = rtrim( $_SERVER['DOCUMENT_ROOT'], '\\/' ); // normalize trailing

		// URL sanitation -------------------------------------------------

		if ( $c->url === null ) // if not $url is passed in (most of the time), take current directory
		{
			// TODO split up, chooping of should happen always,
			//   requiest_uri only be taken if...
			$c->url = strtok( $_SERVER['REQUEST_URI'], '?' ); // chop off any query params
		}

		$c->url = rtrim($c->url, '/'); // normalize slash
		$c->url = rawurldecode($c->url);
		$c->origUrl = rtrim($c->url, '/'); // keep a copy for thumb
		$c->url = utf8_decode($c->url); // !! here, utf8 gets lost !!

		// normalize url to forward slashes
		$c->url = str_replace( '\\', '/', $c->url);

		// needed for title and breadcrumb
		if (empty($c->url)) // root level?
			$this->path = array();
		else
			$this->path = explode( '/', trim($c->url, '/' ));

		$entries = scandir( $this->ROOT.$c->url );
		
		// http://stackoverflow.com/questions/8692764/readdir-vs-scandir
		// COULDDO  "dir /x" hack, around here
		//
		// rough draft:
		// dir /x
		// 11.04.2012  12:20            63.650 WORDPR~3.ZIP wordpress-05_threeKiwis-not-working.zip
		// Match this ( while avoiding matching header/footer crud )
		// ([\d\.]{10})\s+([\d\.]{5})
		// - perhaps beware of different locale time displays?  (12:20pm?)

		enforce(null != $entries);

		// parse directory -------------------------------------------------------------------------
		foreach ( $entries as $entry ) {
			$entryPath = $this->ROOT.$c->url.'/'.$entry;

			//show readme-content (but don't list file itself)
			if( preg_match('/'.$c->README.'/i', $entry) == true ) {
				$lines = @file($entryPath);
				$this->readme_content .= "\n".self::$tabs[$c->indent]."<p>\n";
				foreach ( $lines as $line )
					$this->readme_content .= self::$tabs[$c->indent].trim($line)."<br/>\n";
				$this->readme_content .= self::$tabs[$c->indent]."</p>\n";
			}

			// filtering stage --------------------------------------------------------------
			// make sure, excludes and moot directories don't come through...
			// avoid security breaches by ../sub/../sub ...
			if ( $entry=='.' || $entry==='..' )
				continue;
			// never show (linux-)hidden files or folders
			if ( self::matches( $entry, array('/^\./i') ) )
				continue;
			// Blacklist applies to files and folders
			if (  self::matches( $entry, $this->config->BLACKLIST ) )
				continue;
			// additional stuff not desired to be shown
			if (  self::matches( $entry, $this->config->BLACKLIST_CUSTOM ) )
				continue;
			// Whitelist applies to files (since folders may contain dots, not extension matching)
			if ( !is_dir($entryPath) && !self::matches( $entry, $this->config->WHITELIST ) )
				continue;
			
			$parts = pathinfo($entry);
			$base = $parts['basename']; // full filenname, saves (no) dot hassles..
			$ext = isset( $parts['extension'] ) ? $parts['extension'] : ''; // (defaults to default during rendering)
			$ext = strtolower($ext); // normalize to lowercase

			// COULDDO: dir /x windows hack to support full utf-8 files

			$item = array(
				'type'     => 'file', // (file|link|dir)
				'title'    => 'Ansehen', // for title-attrib aka for hover
				'filename' => ensureUTF8($base), // cater to (Win) local encoding (needed)
				'link'     => rawurlencode(ensureUTF8($base)), // normally equals filename, except lnk
				'ext'      => rawurlencode(strtolower($ext)), // rawurlencode just security
				'icon'     => null, // path to icon
				'size'     => null // stays for directories and CJK files
			);

			if( is_dir($entryPath) ) {
				// handle Directories
				$item['title'] = 'Verzeichnis öffnen';
				$item['type'] = 'dir';
				$this->dirCount++;
				$item['link'].='/'; //tack a slash onto dir-paths

			} else {
				// Handle .url Windows Favorite/Bookmark) Files
				if ( $item['ext'] == 'url' )
				{
					$urlfile = file($this->ROOT.$c->url.'/'.$item['filename']);
					foreach ( $urlfile as $line_num => $line ) {
						if( strstr(trim($line), 'URL=') ) {
							$item['type'] = 'link';
							$item['link'] = trim(substr($line, 4));
							$item['filesize'] = "(link)";
						}
					}
				}
				else if( is_file($entryPath) ) // no size for some UTF-8 files
				{
					$item['size'] = filesize($entryPath);

					// Window issue: negative size could mean very large (GB files)
					if( $item['size'] < 0 && DIRECTORY_SEPARATOR === '\\' ) {
						$filesystem = new COM('Scripting.FileSystemObject');
						$fo = $filesystem->GetFile($entryPath);
						$item['size'] = $fo->Size();
						trace('windows adjustment to '.$item['size']);
					}
				}

				// statistics
				$this->fileCount++;
				// count (thumb-able) images for auto-mode
				if( $item['type']==='file' && isset( self::$thumb_support[$item['ext']] ) )
				{
					$this->thumbCount++;
					$item['thumbID'] = "img".$this->thumbCount; /* needed for slideshow/direct linking */
				}
				
				if( $item['size'] != null ) // n/a for links, undectable for some utf-8 files
					$this->totalSize += $item['size'];
			}

			// determine icon to use:
			// apply icon mappings ( e.g. png, tif => jpg)
			// (actual $ext does survive, needed for thumnail decision)
			$mappedExt = $ext;
			if ( isset( self::$mappings[$ext] ))
				$mappedExt = self::$mappings[$ext];

			switch( $item['type'] )
			{
				case 'file':
				case 'link': // .lnk plays along
					$iconUrl = $mappedExt.'.'.$c->ICON_EXT;
					break;
				case 'dir':
					$iconUrl = $c->ICON_FOLDER;
					break;
				default:
					fail('unknown type');
			}

			$item['icon']=$iconUrl;

			if( $item['type']=='dir' ) //add directories in front
				array_unshift($this->fileList,$item);
			else
				$this->fileList[] = $item;
		} //foreach $entry

		// lastly, prepend parent folder. avoid on website top-level.
		if ( $c->SHOWPARENT && !empty($this->path) )
		{
			array_unshift($this->fileList,
				array(
					'type'     => 'dir',
					'title'    => 'eine Ebene höher',
					'filename' => '.. (one level up)',
					'icon'     => $c->ICON_PARENT,
					'link'     => '..',
					'ext'      => '',
					'size'     => null // stays for directories and CJK files
				)
			);
		}

		// var_dump($this->fileList);
	} //constructor


	/*
	 * ensures that the thumbnails in the indicated (square, boxed) sizes become available
	 * (using Cache::getThumb). If things get close to a page timeout (measure by function from ini etc...
	 * page will issue a (javascript or meta) redirect to itself, to continue the task
	 * (giving a status on the way...)
	 *
	 * ==> to send headers, this function should be called, before ANY php output took place
	 *
	 * @return void will only return (otherwise redirect and exit()) if thumbnailing is done.
	 */
	public function ensureThumbs(array $sizes){

		enforce( is_array($sizes) && !empty($sizes), 'invalid sizes');
		$c =& $this->config; // shorthand

		// aim at most at 70% of permitted timeout time, but prefer 15sec (also to give faster user feedback)
		//
		// note: if this falls under the creation time for a 'worst case thumbnail',
		// this function will FAIL or endlessly loop
		// (extremly rough estimate: 5 secs for a dslr 10-14 MP image, at least on my shared hosting provider)
		//
		// COULDDO: try to detect concurrent thumbnailing (by timestamp touch > 15sec or so )
		//
		$maxTime = (float) ( min(0.7 * ini_get('max_execution_time'), 10.0) );
		Log::info('max Time for thumbNailing:'.$maxTime);

		// COULDDO: estimate based on $o->size
		// the biggest time a single thumbing took so far...
		$thumbsDone = array();
		foreach( $sizes as $size )
		{
			foreach( $this->fileList as $f )
			{
				$o = (object)$f;
				if( $o->type!=='file' || !isset( self::$thumb_support[$o->ext] ) )
					continue;

				Log::info("doing $c->origUrl $o->filename  $o->ext");
				$url = Cache::getThumb( $c->origUrl.'/'.$o->filename, 'b', $size, $size, false, true, $c->thumbScale, $imgW, $imgH );
				$thumbsDone[] = $o->filename;

				Log::info("total time spent: ".$this->timeSpent());
				Log::info("-------------------------------------------");


				if( $this->timeSpent() > $maxTime )
				{
					header('Cache-Control: no-cache, must-revalidate');
					header('Expires: Mon, 26 Jul 1990 05:00:00 GMT');
					header('Pragma: no-cache');
					require('templates/ensureThumbRedirect.inc.php');

					Log::info( count($thumbsDone).' thumbs processed in '.sprintf('%.2f',$this->timeSpent()).'seconds.');
					Log::info( 'REDIRECT');

					exit(); // avoid trailing crud
				}

			} // foreach $f
		} // foreach $size

		if( $this->timeSpent() > $maxTime )
		{
			//one last redirect to have enough time for actual page rendering
			out('<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0"></head>'.
				'<body>rendering page...</body></html>');
			exit();
		}

		Log::info( 'DONE:'. count($thumbsDone).' thumbs generated in '.sprintf('%.2f',$this->timeSpent()).'seconds ...');

		// (lastly, return and just normally render the page)
	}

	/**
	 * extracts metadata from files - in a lazy fashion
	 *
	 * @param bool $force force metadata (re)building
	 */
	public function ensureMetadata($force=false) {

		$c =& $this->config; // shorthand
		Log::info('=== meta ==?============================');
		foreach( $this->fileList as $f )
		{
			$o = (object)$f;

			if( $o->type!=='file' || !isset( self::$meta_support[$o->ext] ) )
				continue;

			$info = false;
			$file = $this->ROOT.$c->origUrl.'/'.$o->filename;
			$sidecar = $this->ROOT.$c->origUrl.'/'.$o->filename.'.meta';
			enforce(is_file($file));

			if ( !$force && isNewer($sidecar, $file) )
				continue;

			$json = Meta::getAsJson($file);

			// (nb: even an empty json is good to write, to lazily avoid future extraction attempts)
			$sidecar = fopen( $sidecar, "w");
			fwrite($sidecar, json_encode($json));
			fclose($sidecar);
		}
	} // ensureMetadata


	/**
	 * get's the last piece of the folder URL
	 * note: for SEO consideration and/or better orientationm
	 * you may want to use breadcrumb to fill the title tag
	 * (e.g. implode with pipe operator)
	 */
	public function title() {
		if (empty($this->path)) // on top leve, use host name
			return htmlentities( $_SERVER['HTTP_HOST'] );
		else
			return htmlentities( end($this->path) );
	}

	/**
	 * gets you the content of the (resp. all) reamdme file(s)
	 * - aggregation happens beforehand. calling this several times isn't expensive
	 *
	 * @return string
	 */
	public function readme() {
		return $this->readme_content;
	}

	// returns the url (which ist most often the current dir, but could be passed-in)
	// without any trailing query params ==> good for breadcrumb and title use
	public function url() {
		return $this->config->url;
	}

	/**
	 * @return total Number of listed files
	 * ( excludes directories, includes .url files)
	 */
	public function totalFiles() {
		return $this->fileCount;
	}

	/**
	 * @return total Number of listed subdirectories
	 */
	public function totalDirs() {
		return $this->dirCount;
	}

	/**
	 * @return total Size of listed files in plain bytes
	 */
	public function totalSize() {
		return $this->totalSize;
	}

	/**
	 * @return total Size of listed files in bytes, kb, MB, GB
	 */
	public function totalSizeHuman( $precision = 0 ) {
		return self::humanSize( $this->totalSize, $precision );
	}

	/* outputs an array of arrays contianing
	 * * title and
	 * * server-absolute (and properly encoded) url
	 */
	public function breadcrumb( $simple = false ) {
		$url = '/';
		$r = array();
		foreach ( $this->path as $c ) {
			if ( $simple )
			{
				$r[] = htmlentities($c);
				continue;
			}
			$url .= rawurlencode($c).'/';
			$r[] = array(
				'title' => htmlentities($c),
				'url' => $url
			);
		}
		return $r;
	}

	/**
	 * actually renders the file set
	 */
	public function render( $mode = 'auto', $size='auto' ) {

		$c =& $this->config; // shorthand
		out('');

		if ( $mode==='auto' ) // TODO, based on '$thumb_support stats'
		{
			// do not count readme for decision (otherwise 2 pics and one readme would be only 66% ...)
			// ignorereadmeCompensate (otherwise 2 pics and one readme would be only 66% ...)
			$total = $this->totalFiles() - ( empty($this->readme_content) ? 0 : 1 );

			if ( $total > 0 && $this->thumbCount / $total > $c->GRID_THRESHOLD )
				$mode = 'grid';
			else
				$mode = 'list';
		}
		if ( $size==='auto' ) // TODO, based on numbers (and $mode). Less items, bigger default
		{
			if ( $mode==='gallery' )
				$size = 1280;
			if ( $mode==='grid' )
				$size = 128;
			else
				$size = 32;
		}
		enforce( is_int($size) );

		if ( $mode==='gallery')
		{
			foreach ( $this->fileList as $file ) {
				
				$o = (object) $file; // shorthand
				
				// only images matter
				if ( !isset( self::$thumb_support[$o->ext] ) )
						continue;
				
				$url = Cache::getThumb( $c->origUrl.'/'.$o->filename, 'b', $size, $size, false, true, $c->thumbScale, $imgW, $imgH );
				out( "<a href='$url' id='".$file['thumbID']."'></a>" );
			}			
			return;
		}
		
		//for (potentially larger thumbs)
		enforce( in_array($size, array(32,64,128,256)),'non-available icon size' );
		// OLD enforce( in_array($thumbSize, Cache::$PERMITTED_SIZES),'invalid thumb size' );

		$ulClass = array("dirList");
		$ulClass[] = "dirList-$mode";
		$ulClass[] = "dirList-$size";
		$ulClass[] = "dirList-$mode-$size";

		/* use singleCol rather than styling dirList list width 780px
		 * since singeCol is already adjust-styled for Mobile... */
		if ( $mode === 'list' )
			$ulClass[] = 'singleCol';

		outPush("<ul class='".implode(' ', $ulClass)."'>",$c->indent);
		if( count($this->fileList) < 2 )  //if empty aka only .. in there
			out("<li class='item item-empty'>This directory is empty.</li>");

		// odd-even class
		$even = true;

		// iterate file-list
		foreach ( $this->fileList as $file ) {
			$o = (object) $file; // shorthand

			$liClass = array('item');
			if ($even)
				$liClass[] = 'item-even';
			$even = !$even;

			// links open in external tab
			$target = ($o->type === 'link') ? " target='_blank'" : '';

			// single-column (folders and other no-downloaders)
			$hasDownload = ( $o->type === 'file' );
			$singleCol = !$hasDownload;
			if ($singleCol)
				$liClass[] = 'item-singleCol';

			// computer right icon and path, default fallback.
			$iconBaseUrl   = $c->ICONURL.$size.'/';
			$iconUrl = $iconBaseUrl.$o->icon;

			$iconPath = $this->ROOT.'/'.$c->ICONPATH.$size.'/';

			if( !is_file( $iconPath.$o->icon ))
			{
				error_log("missing ICONPATH: ".$iconPath.$o->icon);
				$iconUrl = $iconBaseUrl.$c->ICON_DEFAULT;
			}

			//get prior .meta(data) file
			$meta = array(); // assume empty array for now
			$metaFile = $this->ROOT.$c->origUrl.'/'.$o->filename.'.meta';
			if ( is_file($metaFile) )
			{
				$metaString = file_get_contents($metaFile);
				$metaString = str_replace('\\\\r\\\\n', '<br>', $metaString);
				$meta = json_decode( $metaString );
			}

			$title = $o->filename;
			if( isset( $meta->caption ) )
				$title = json_clean($meta->caption);

			// COULDO:
			// $download-Only stuff gets singleCol and download-Button...

			$iconOrThumb = isset( self::$thumb_support[$o->ext] ) ? 'thumb' : 'icon';
			$liClass[] = $iconOrThumb;
			outPush("<li class='".implode(' ',$liClass)."'>");
			
			$thumbID = isset($o->thumbID) ? " data-thumbid='$o->thumbID'" : '';
			{
				outPush("<a class='view'$thumbID$target href='$o->link' title='$o->title'>");
				{
					if ( $iconOrThumb==='thumb' )
					{
						$imgW = $imgH = 0;
						
						$url = Cache::getThumb( $c->origUrl.'/'.$o->filename, 'b', $size, $size, false, true, $c->thumbScale, $imgW, $imgH );

						$marginTop = floor(($size-$imgH)/2) .'px';
						$marginLeft = floor(($size-$imgW)/2) .'px';
						out("<img src='$url' alt='$title' width='$imgW' height='$imgH' style='margin-top:$marginTop;margin-left:$marginLeft'>");
					}
					else
					{
						out("<img src='$iconUrl' alt='' width='$size' height='$size'>");
					}
					
					if ( $o->size !== null && $o->size !== '' )
						out("<span class='filesize'>".self::humanSize($o->size)."</span>");
					out("<span class='filename'>$title</span>");
				}
				outPop("</a>");

				if( $hasDownload )
					out("<a class='download neverprint' title='Herunterladen' href='?download=$o->link'><span>Download</span></a>");

			}
			outPop("</li>");

		} // foreach $file

		outPop("</ul>");
		if ($mode!=='list')
			out("<hr class='clear'/>");
		out("<div style='text-align:center;font-size: x-small;color:#888'>powered by ".
			"<a href='http://github.com/fran-kee/dirList' target='_blank'>".
			"dirList</a></div>");

	} // dirList()

} // class
