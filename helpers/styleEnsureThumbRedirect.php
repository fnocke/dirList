<?php
/**
 * a little helper to help you style the ensureRedict status box
 *
 */


require_once '../inc/Log.php';
require_once '../inc/Cache.php';
require_once '../inc/common.inc.php';

//mock
class Mock {

	public $fileList;

	private function timeSpent() {
		return 4.4242;
	}

	public function mock() {

		$this->fileList = array_fill( 0,42, 'mockfile' );
		$thumbsDone = 14;
		$sizes = array( 32, 192 );
		$thumbsDone = array(
			'IMG_1234.jpg',
			'IMG_1235.jpg',
			'IMG_1236_Jürgen_Köhler.jpg',
			'Banana Boat.png',
			'Test Foo Bar this is really long.jpg',
			'IMG_1472.jpg'
		);

		require_once '../inc/templates/ensureThumbRedirect.inc.php';

	}


};

$m = new Mock();
$m->mock();

