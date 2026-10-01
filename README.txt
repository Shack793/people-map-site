YOUR SITE + ADMIN BACK END
==========================

WHAT'S IN THIS FOLDER
  index.html   the website (Updates, Team chart, admin dashboard)
  api.php      the back end: login, saving, image and video uploads
  seed.json    your current content; copied into the back end the first time it runs
  admin/       shortcut, so yoursite.com/admin opens the login
  data/        where your content and password are kept (not readable from the web)
  uploads/     where your images and videos are kept

REQUIREMENTS
  Any web host with PHP 7.4 or newer. Nearly all regular hosts have it:
  GoDaddy, Hostinger, Bluehost, Namecheap, SiteGround, any cPanel host.
  Static-only hosts (GitHub Pages, plain Netlify/Vercel) can't run PHP.

SETUP (about 5 minutes)
  1. Upload EVERYTHING in this folder to your site, keeping the folders as they are.
     If you uploaded an earlier version, replace index.html with this one.
  2. Open  yoursite.com/admin
  3. Enter this one-time setup key:

         ZNU6-3CKC-SK2Y-835B

     then choose your admin password (at least 8 characters).
  4. You're in. From then on, log in at yoursite.com/admin with your password.

     Keep the setup key private until you've done step 3. After the password
     is set, the key can't be used again.

USING THE ADMIN
  Overview  quick links: write a post, add a person, upload images, change logo & text
  People    add, edit, remove people; set who reports to whom; photos
  Posts     write and edit posts with images, video, links and buttons; drafts
  Media     your image and video library (upload, see where each file is used)
  Settings  site name, logo, colors, text, home page layout, and your admin password
  When you're logged in, the public site shows a small Admin button in the corner.
  Visitors never see it.

IF SOMETHING GOES WRONG
  "folder is not writable": in your host's File Manager, set the data and
    uploads folders to permission 755 (or 775).
  Large uploads fail: your host limits upload size. Files up to 20 MB are
    supported; ask your host to raise "upload_max_filesize" and "post_max_size"
    if needed.
  Forgot your password: delete the file data/auth.php using your host's
    File Manager, then open yoursite.com/admin and use the setup key again.

BACKUPS
  Your content is in data/site.php and your files are in uploads/.
  Download those two folders to back everything up.
