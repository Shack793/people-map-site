YOUR SITE + ADMIN BACK END  (with search engine optimization)
=========================================================

UPDATING A SITE YOU ALREADY HAVE
  Upload everything in this folder over your current files.
  The team chart becomes your home page the first time the site is opened after
  the upload. To change it later: Admin > Settings > Pages > Home page.
  Your people, posts, photos and password are kept: they live in data/ and
  uploads/, which this upload doesn't overwrite.
  If your host already had a file called .htaccess in that folder, keep its
  existing lines and paste the lines from this one underneath them.

WHAT'S IN THIS FOLDER
  index.php         builds every page for visitors AND search engines
  app-template.php  the page design used by index.php (not viewable directly)
  site-lib.php      shared code for the pages (not viewable directly)
  api.php           the back end: login, saving, image and video uploads
  sitemap.php       list of all your pages for Google  -> yoursite.com/sitemap.xml
  robots.php        instructions for search engines    -> yoursite.com/robots.txt
  robots.txt        fallback for hosts without .htaccess support
  feed.php          RSS feed of your posts             -> yoursite.com/feed
  favicon.php       your logo as the browser-tab and Google icon
  .htaccess         clean page addresses (/team, /updates/...) and protections
  index.html        forwards old links to index.php
  seed.json         starting content for a brand-new install only
  admin/            shortcut, so yoursite.com/admin opens the login
  data/  uploads/   your content and files (kept private by the server)

PAGE ADDRESSES
  Home (team chart)     yoursite.com/
  All updates           yoursite.com/updates
  Each post             yoursite.com/updates/<id>/<post-title>
  If your host doesn't support .htaccess, the same pages use
  yoursite.com/?page=team and yoursite.com/?post=<id> instead. Both work.

WHAT'S BEEN DONE FOR SEO
  - Every post and the team page has its own address that Google can index.
  - Pages are built on the server, so search engines and link previews
    (WhatsApp, Facebook, X, LinkedIn) see the full text without running JavaScript.
  - Each page has its own title, description and canonical address.
  - Link previews show the title, description and an image.
  - Structured data: articles for posts, people for the team, your organization
    and website, and breadcrumbs.
  - Sitemap, robots.txt, RSS feed, favicon, image descriptions (alt text).
  - The admin area and back end are kept out of search results.

IN THE ADMIN (Settings and the post editor)
  Settings > Text: "Site description for Google and link previews".
    One or two sentences about you. This is what shows under your name in Google.
  Settings > Text: "Image for link previews". A wide image (1200 x 630) shown
    when your site is shared. Posts with a cover image use their own.
  Post editor: "Summary for Google and link previews" (optional). Leave empty
    and the start of the post is used.

TELL GOOGLE ABOUT THE SITE (once)
  Go to search.google.com/search-console, add your site, then under Sitemaps
  enter:  sitemap.xml

FIRST-TIME SETUP (brand-new install only)
  1. Upload everything in this folder to your site, keeping the folders as they are.
  2. Open  yoursite.com/admin
  3. Enter this one-time setup key:  ZNU6-3CKC-SK2Y-835B
     then choose your admin password (at least 8 characters).
  4. From then on, log in at yoursite.com/admin with your password.

REQUIREMENTS
  Any web host with PHP 7.4 or newer (GoDaddy, Hostinger, Bluehost, Namecheap,
  SiteGround, any cPanel host).

IF SOMETHING GOES WRONG
  "folder is not writable": in your host's File Manager, set the data and
    uploads folders to permission 755 (or 775).
  Large uploads fail: your host limits upload size. Ask your host to raise
    "upload_max_filesize" and "post_max_size".
  Forgot your password: delete data/auth.php in your host's File Manager, then
    open yoursite.com/admin and use the setup key again.

BACKUPS
  Download the data/ and uploads/ folders to back everything up.
