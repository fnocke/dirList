<?php
/**
 * if one is using never-expires for his resources (icons, js, css, ...)
 * he either has to keep renaming files ( sprites2.png, sprites3.png)
 * or -even worse- deal with clear-cache advice
 * (and it is questionable if all proxies on the way will get the new one)
 *
 * This simple trick fills this gap:
 *
 * - returns the url plus an added current timestamp
 * - typically one will pass in only references to the static folder
 *   for which a rewrite rule extists, rewriting away the timestamp again
 *   ==> smart never expires
 *
 * You want something like this in your .htacces:
 * # rewrite-away timestamp:
 * RewriteRule ^static/(.*)/\d{9,11}$    /somewhere/static1234/$1  [NC,L]
 * (remember if your rewrite-rule is in a .htaccess not on toplevel, but say, in subfolder /foo/bar
 *  you may need to prepend /foo/bar to the right side of your rewrite rule)
 *
 * @author franknocke@gmail.com
 */
class Statics {
	// actual configuration
	private static $config = false;

	// default configuration
	private static $defaultConfig = array(
		/** @var string base, where to find your static folder (i.e. to grab those timestamp) */
		'STATICPATH'      => '/static/',
		/** @var string where to request it (starts with '/' or e.g. http://static.foo.com/') */
		'STATICURL'        => '/static/',
		/** @var boolean allows to disable timestamp appending */
		'REWRITE' => true
	);

	/**
	 * one initial call of config() is required. multiple calls won't hurt.
	 */
	public static function config(array $config = array())
	{
		// merge given (precedence) and default config
		$ROOT = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ); // normalize trailing
		self::$config = (object) array_merge( self::$defaultConfig, $config);
		
		$c =& self::$config; // shorthand
		$c->STATICPATH = $ROOT.'/'.$c->STATICPATH; //prepend
	}

	/**
	*  @param string $filePath - path+filename (often just filename)
	 * relative to physical_path AND request_url, no leading slash
	*/
	public static function get( $filePath )
	{
		$c =& self::$config; // shorthand

		enforce(
			is_file( $c->STATICPATH.$filePath ),
			'getStatic: requested file '.$c->STATICPATH.$filePath.' does not exist'
		);
		
		$ts = filemtime( $c->STATICPATH.$filePath );
		return $c->STATICURL.$filePath. ( $c->REWRITE ? ('-'.$ts) : ''); // go through rewrite
	}
}
