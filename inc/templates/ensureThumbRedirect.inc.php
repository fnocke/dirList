<!DOCTYPE html>
<html lang="de-DE">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>Rebuilding thumbs... <?=count($thumbsDone).' of '.( count($sizes) * count($this->fileList));?></title>
		<?php /* I -did- see this inbetween page in googles index.. the revisit-after is probably pointless */?>

		<meta name="robots" content="noindex">
		<meta name="revisit-after" content="2 hour">

		<?php
			if ( \get_class($this) === 'DirList' ) {   //skip in style mocking to avoid neverending loops
				out('<meta http-equiv="refresh" content="1">');
			}
		?>
		
		<link rel="stylesheet" type="text/css" media="all" href="<?=Cache::getStatic('/static/dirList-styles.css')?>" />
	</head>
	<body class='dirList-redirect'>
		Please be patient for a moment, while generating thumbnails...
		<?php
			out( count($thumbsDone).' of '.( count($sizes) * count($this->fileList) ).
					' thumbs processed in '.sprintf('%.2f',$this->timeSpent()).' seconds.');
			out( implodeX('<ul><li>', $thumbsDone, '</li></ul>', '</li><li>'));
		?>
		... still working ...
	</body>
</html>
