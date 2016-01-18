<?php
	require_once 'common.inc.php';
	require_once('Log.php');

	/**
	* temporarily used in Metadata parsing
	*/
	function ignoreAnyError($errno, $errmsg, $filename, $linenum, $var )
	{
		Log::info("ignoreAnyError: gracefully ignored something in $filename : $linenum");
	}

	/**
	 * Allows for simple IPTC, EXIF metadata extraction from JPEGs
	 * (possibly tif, psd) using on-board methods in PHP
	 * and open the template in the editor.
	 *
	 * $file (absolute) path to the file
	 */
	class Meta {

		public static function getAsJson($file, $force=false) {
			$json = array();

			getimagesize( $file, $info );

			if ( isset($info["APP13"] ))
			{
				$iptc = iptcparse($info["APP13"]);

				if ( isset( $iptc['2#120'] ))
				{
					$caption = implode( '|', $iptc['2#120']); // nb: '|' should never actually appear
					$caption = ensureUTF8( $caption ); // since could be 'local' encoding
					$caption = mysql_escape_string( $caption ); // safety. stackoverflow.com/q/1162491
					$json['caption'] = $caption;
				}

				if ( isset( $iptc['2#025'] ))
					$json['keywords'] = $iptc['2#025']; // keep as array

				set_error_handler("ignoreAnyError", E_ALL );
				{
					// TOTEST, currently no exif enabled on localhost
					if (function_exists('exif_read_data'))
						$json['exif'] = exif_read_data( $tmpfile, 0, false ); // fails on a very few files (corrupt EXIF)
				}
				restore_error_handler();

			}

			Log::info( "json for :".$file );
			Log::info( $json );
			return $json;

		}

	} // class Meta

?>
