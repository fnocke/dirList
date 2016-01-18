dirList V0.8
================================

## A php library / class to automatically and nicely list directory contents | autoIndex, auto indexing, directory listing, index browsing.| No external libraries used except gdlib. Easy to ingegrate. Or basically: throw your files into your webspace, and this script will take care, that they're presented as nicely and conveniently as possible.

DirList traverses the current (or, configurably, any other) directory and shows its contents in much
nicer ways than regular apache 'autoindexing' does. It generates lean, well-stylable, easy to integrate markup.

*NEW*: [Live «Demo» here.](http://fotos.nocke.de/Sport/2012-06-11%20smart%20Beachtunier%20Hafencity%20Hamburg/)

Efficient, positively lazy, automatic thumbnailing, automatic iptc metadata support. Smart never-expire caching strategy for thumbs (Class Cache) and other (Class Statics) ressources. Automatic (if desired) switching between list (mixed documents)and thumb (mostly images) mode. Full use of screen width for thumbs (not bound to 780/960px). Helpers for title tag and breadcrumb navigation.

![screenshot](http://github.com/fran-kee/dirList/raw/master/screenshot.jpg)

## Feature List

* dedicated download button, triggering the brower's download dialog
* java-script free, everything based on li,a :hover attribute. Yet, well-prepared for your jQuery-needs.
* friendly selectors, for easy jQuery / prototype application, though
* but of course: carefully chosen classes for easy selection and manipuliation
* security considerations: black- AND white-listing.
* thumbnail-support for jpg and png
* supports for 32px and 128px thumbs/icons in list mode (64 and 256 only lack css styles)
* smart caching for icons and thumbs (never-expires yet never refresh problems)
* css with smart, timestamp-based never-expire
* tolerant handling of broken/half-uploaded files. (thumbnailer fails gracefully and will retry if upload continues (i.e: timestamp gets newer))
* support for (CSS3 transform:scale) thumbnails through DirList and Thumbnailer
* Thumb ensurance/generation (and redirect loop to prevent timeout)
* automatic switch of size (plenty of files) and mode ( >80% images => grid )
* no obsolete '..' on top-level
* easy support for static CDN-ish domains, for even faster loading.
* support for rewrite-obfuscated resource (icons, css) folders
* support for custom blacklist BLACKLIST_CUSTOM (on top of default)

## BUGS

* please report them to me.


## TODO List

* address captions/filename on very long strings. Ensure hovering title tab has it.
* fix JSON de-encoding for \" quotes. Maybe more.
* thumb view: Maybe keep size info for hover

* Thumnail extract for raws (maybe PDFs etc.) using exiftool) D:\Documents\Pictures\TEST_RAW_EXTRACT

* more descriptive progress in rebuilding-thumbs title...
 (because this could be all you get to see...)

* http://ryanfait.com/sticky-footer/layout.css

* download-all option: download http://www.phpclasses.org/package/945-PHP-Create-tar-gzip-bzip2-zip-extract-tar-gzip-bzip2-.html
  http://stackoverflow.com/questions/1061710/php-zip-files-on-the-fly
* didn't know backtick operator (also for dir /X ?), http://www.php.net/manual/en/language.operators.execution.php
  (anyway, let's stick with system)

* specific download-only links for some file types (zip, jar, arguably pdf, config-based)
* hard configurable top-levels (serve as whitelists at the same time, and ommit "one level up"). Empty means no such thing

* channel support for Log class
* dir /X mode (for proper unicode access under Win. No, there is no other way.)

* flexible register-addOn?
  * rating

* 'view' support (smaller size, hower, hash domain. resolution / mobile fork. )
  * view support for plaintext (maybe markdown, ...)

* stats ( <-> canonical url)
  * page stats
  * view stats

* investigate: <link rel="canonical" href="http://www.foo.de/bar/doo/ree67833478956.html" />
* navigation modes, incl. faithful $_GET-preservation
* exiftool extended thumbnail support

* detail list view?

* list with extra data to allow for plenty of extra data (auto-height, just min-height)

* support readme.md (markdown)

* authentication
  * htaccess-user
  * login ( private link + pw request )
  * -> upload by button
  * -> upload by drag
  * delete
  * rename
  * ftp-Push

* integrate (anonymous, accumulated canonical url irrespective of filters, sort order and inidividual file usage stats) statistics
  
*  provide cache cleanup convenience utilitiy,
   ideally based on last access timestamp
   http://www.howtogeek.com/howto/ubuntu/get-last-accessed-file-time-in-ubuntu-linux/
  
* no-direct-download-mode (might require some if-exists-.htaccess? Or obfuscative prefix for all files, (a hash, salted with a server-side secret?)



## License - GNU LGPL 3.0 - Lesser General Public License 

All Files contained in this folder and its subfolders are part of the  dirList Project (henceforth: dirList).

This includes the (partly modified) icons under static/icons which stem from the Everaldo Crystal Project,
which are equally under LGPL. See static/icons/readme.txt for details.

This excludes the 3rd party projects within the static1234/lib folder, which may be under different licenses. 
See the respective README resp. LICENSE file in these folders for details.

dirList is free software: You may redistribute it and/or modify it under the terms of the GNU General Public License
as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
You should have received a copy of the GNU General Public License along with dirList. ( See COPYING and COPYING.LESSER )
If not, see <http://www.gnu.org/licenses/>.

THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
SUCH DAMAGE. 


-----------------------------------

Usage / Installation
-----------------------------------

See `template.php` which should get you the picture.

    $dl = new DirList( /* possible config array */ );

Whatever you decare cache directory (see Cache::CACHEDIR, by default '_cache' ) must have proper rights,
such that the webuser can create files and subdirectories, i.e. chmod 775, maybe even chmod 777 
(by command line or ftp client)

	CACHEDIR = '_cache/';

The folder which (including it's subfolders) you intend to dirList,
should contain a .htaccess with these contents:

	# Just to avoid any refresh issues (since folder contents may vary
	# quickly, i.e. during uploads)
	Header set Cache-Control: "max-age=0, no-store"

	# Place these two lines *after* all your rewriting rules (if any).
	# note: You may have preceding index-files if you want. Example:
	# DirectoryIndex index.php index.html dummy.html /template.php
	# note: +Indexes is not required
	# note: Yes, we are aiming at an absolute path from root
	DirectoryIndex /template.php
	Action  application/x-httpd-parse /template.php

Enjoy :-)

