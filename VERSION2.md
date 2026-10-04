# Version 2 ideas

Everything below was noticed during Version 1 but deliberately left out, to keep each sprint small.
Nothing here is promised. Pick items when Version 1 is live and you know what visitors actually need.

## Admin
- Drag-and-drop re-ordering of a restaurant's gallery photos.
- Split opening hours (for example lunch 11:00-14:00 and dinner 18:00-22:00 on the same day).
- Show the live URL slug while typing a name in the admin forms (today it is filled in when you save).
- Cuisine and amenity lists in the admin: replace the per-row restaurant counts with the faster grouped query used on the public site.

## Public site
- An **"Open now"** badge on restaurant pages (needs careful handling of hours that run past midnight and of time zones).
- Show a city's description as an introduction on its public `/city/{slug}` page (the field exists in the admin since Sprint 7).
- Photo lightbox (view gallery photos without leaving the page).
- Map on the restaurant page.

## Speed and scale (only needed with thousands of restaurants)
- Store each restaurant's average rating and review count on the restaurant itself, so "Top rated" sorting needs no calculation (about 240 ms at 5,000 restaurants today).
- Real full-text search instead of "contains the word" (about 80 ms at 5,000 restaurants today).
- Resize uploaded photos into small, medium and large versions (today the original, up to 3 MB, is used for cards).
- Split `sitemap.xml` into several files (a sitemap index) once there are more than 50,000 addresses.

## Security
- A Content-Security-Policy header. It needs the small inline scripts (such as the delete confirmation) moved into `app.js` first.

## Out of scope for Version 1 by decision
User accounts, online booking, payments, multiple languages.
