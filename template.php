<?php 
   /* You probably want to use this file literally, but as a guidance for your own template.php file.
    * Just take what you need. */

	require_once 'inc/common.inc.php';
	require_once 'inc/Statics.php';
	require_once 'inc/Cache.php';
	require_once 'inc/DirList.class.php';


	$SLIDESHOWSIZE = 1280;
	
	Statics::config(array(
		'STATICPATH' => '/static1234/',
		'REWRITE' => false
		// ,'STATICURL' => 'http://staticlocal.dirlist/'
	));

	Cache::config(array(
		'CACHEPATH' => '/_cache/', 			// physical file-system path
		'CACHEURL' => '/cache/',          // relative to same domain, or another, 'static' domain for performance, i.e. http://cachelocal.dirlist/',
		'BROKENPATH' => '/static1234/icon/broken.png' // path to broken image
	));

	$d = new DirList( array(
		// insert config parameters here, to override defaults (see DirList class)
		'ICONPATH' => '/static1234/icon/',    // actual physical path rel. to document root
		'ICONURL'  => '/static/icon/'        // root-relative or absolute web access to it
		// example for a static domain 'ICONURL'  => 'http://staticlocal.dirlist/icon/',
		
		// 'url' => '/sample Folder/sub/',
		// 'BLACKLIST_CUSTOM' => array( '/^(pages|privat|readme.txt)$/i' )
	));

	$bodyClasses = array('body');
	// helpful for a body.mobile class to allow mobile-specific styling
	if( isMobile(/* to dev-fake mobile: 'askdahkd iPod as' */ ))
		$bodyClasses[] = 'mobile';

	// catch and deal with any download requests. call this before any ensure-Functions
	$d->checkDownload();
	
	// lazy 'ensure' Functions ==> call before any output (i.e. header), to allow for redirect (in ensureThumbs)
	$d->ensureThumbs( array(32,128,$SLIDESHOWSIZE) );
	$d->ensureMetadata();
?>
<!DOCTYPE HTML>
<html lang="de-DE">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title><?php echo implode(' | ', $d->path )?></title>
		<link rel="stylesheet" type="text/css" media="all" href="<?php echo Statics::get('general.css')?>" />
		<link rel="stylesheet" type="text/css" media="all" href="<?php echo Statics::get('dirList.css')?>" />
		<link rel="stylesheet" type="text/css" media="all" href="<?php echo Statics::get('lib/fotorama/fotorama.min.css')?>" />
		<meta name="viewport" content="width=600px, initial-scale=0.5, user-scalable=yes">
		<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico" />
		<link rel="icon" type="image/x-icon" href="/favicon.ico" />
	</head>
	<body<?php echo implodeX(" class='", $bodyClasses, "'", " ")?>>
		<div id='head'>
			<div class='singleCol'>
			<?php
				out( "<a href='/'>".$_SERVER['HTTP_HOST']."</a> " ); // just a suggestion
				foreach( $d->breadcrumb() as $b )
					out( ' &gt; <a href="'.$b['url'].'">'.$b['title'].'</a> ' );

			?>
			</div>
		</div>
		<?php
			if ( isMobile() )
				out('<h2 class="singleCol">(mobile version)</h2>');

			// you may or may not want to put your images under CC-BY, and indicate
			// that with a graphic badge in your readme box. I use a not-listed marker-file to achive that:
			if ( is_file( $ROOT.$_SERVER['REQUEST_URI'].'CC-BY.marker') )
			{
				out('<div class="singleCol"><a href="http://creativecommons.org/licenses/by/3.0/de/" target="_blank">');
				out('<img src="/static/cc-by-88x31.png" width="88" height="31" align="right" style="margin:10px" title="Creative Commons CC-BY 3.0">');
				out('</a></div>');
			};

			$title = $d->title();
			out("<h1 class='singleCol'>$title</h1>");

			$readmeText = $d->readme();
			if ( !empty($readmeText) )
			{
				// only push out readme-box, if there is a readme
				outPush('<div id="readme" class="singleCol">');
				out( $readmeText );
				out('<hr class="clear"/>');
				outPop('</div>');
				out();
			}

			$d->render();

			// alternative, less automatic outputs:
			// 
			// out("<h2 class='singleCol'>128px list</h2>");
			// $d->render('list',128);

			// out("<h2 class='singleCol'>128px grid</h2>");
			// $d->render('grid',128)
		?>

		<?php // slideshow integration ?>
			<div id="fotorama" 
				 data-auto="false"
				 data-width="100%" 
				 data-height="95%" 
				 data-keyboard="true"
				 data-allowfullscreen="native"
				 data-hash="true"
				 data-shadows="true"
				 data-loop="true"
				 data-arrows="true"
				 >
				 <?php $d->render('gallery',1280); ?>
			</div>
		
		<a class='buttonM slideshow-play'>Play Slideshow</a>
		
		<?php /* example foot, making use of dirList statistical functions */?>
		<div id="foot" class='singleCol'>
			Folder contains <?php echo $d->totalSizeHuman(2)?> in <?php echo $d->totalFiles()?> Files. It has <?php echo $d->totalDirs()?> subdirectories. check <a href='http://validator.w3.org/check?uri=referer'>W3C</a>
		</div>
		
		<!-- script foot -->
		<script src='http://code.jquery.com/jquery-1.11.0.min.js'></script>
		<script src='<?php echo Statics::get('lib/fotorama/fotorama.min.js')?>'></script>
		<script src='<?php echo Statics::get('common.js')?>'></script>
		
	</body>
</html>
