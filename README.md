# People map + Updates site

## Files
- **index.html**: the full site: org chart, profiles, Updates page, post editor and admin mode.
- **preview.html**: a read-only copy with your current people, posts and settings built in. Open it in any browser; no login or server needed.
- **data/**: your current content as JSON (`people.json`, `posts.json`, `settings.json`).

## How saving works
index.html stores everything through the claude.ai artifact runtime (`window.claude.use("db")`
for data, `use("assets")` for uploaded images and videos, `use("user")` to check who can edit).
Outside claude.ai those aren't available, so the page falls back to the built-in sample content,
read-only.

To run the editable version on your own host, replace the calls in the "live data" block at the
bottom of the script (and `savePerson`, `removePerson`, `savePost`, `removePost`, the settings save,
and `uploadImage`) with your own backend, such as Firebase, Supabase or a small API.

## Admin dashboard
On the live site, the owner and people with Editor access see a small **Admin** button in the
bottom-right corner (or add `#admin` to the link). The dashboard has Overview, People, Posts,
Media (image and video library) and Settings. Visitors never see it.
