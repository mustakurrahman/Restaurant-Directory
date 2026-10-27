# Manual QA checklist

Tick every box on **desktop** (browser at full width) and **phone** (press F12, click the phone icon, pick "iPhone 12", or open the site on a real phone at `http://YOUR-PC-IP:8000`).
Do this before every release. Note anything wrong with the page address and what you did.

**Every page, every time (the "universal five"):**
- [ ] No horizontal scrollbar on phone (nothing sticks out past the screen edge)
- [ ] Text is readable (cream on dark), buttons are big enough to tap
- [ ] Images load (or show the placeholder), none are stretched
- [ ] The browser tab has a sensible title
- [ ] No red error page and no "Whoops"

---

## Public pages

### Homepage `/`
- [ ] Header, logo and menu show. **Phone:** the Menu button opens and closes the menu
- [ ] Search box works and goes to `/restaurants`
- [ ] Numbers strip, featured restaurants, cities, cuisines and "newly added" sections show
- [ ] Every card, city and cuisine link opens the right page
- [ ] Footer links work

### Restaurant listing `/restaurants`
- [ ] 12 restaurants per page; page links work and keep your filters
- [ ] Keyword search finds a restaurant by name and by address
- [ ] City, cuisine, price and amenity filters each narrow the list; combined filters narrow it further
- [ ] Filter chips appear and the "x" removes one
- [ ] Every sort option changes the order
- [ ] A search with no results shows a friendly message, not an error
- [ ] **Phone:** the filter panel opens, is usable and closes
- [ ] Drafts never appear

### Cities `/cities` and city page `/city/{slug}`
- [ ] All cities listed with counts; each opens
- [ ] City page lists only that city's published restaurants; sorting and paging work
- [ ] `/city/does-not-exist` shows the branded 404

### Cuisines `/cuisines` and cuisine page `/cuisine/{slug}`
- [ ] Same checks as cities

### Restaurant page `/restaurant/{slug}`
- [ ] Name, address, phone, website, price, cuisines and amenities show
- [ ] Cover photo and gallery show; opening hours are listed Monday first with today highlighted
- [ ] Only approved reviews show, with the average rating
- [ ] Phone and website links work (phone link dials on a phone)
- [ ] "Other restaurants in this city" shows
- [ ] A **draft** restaurant's address shows the 404 page

### Review form (on the restaurant page)
- [ ] Empty submit shows errors and keeps what you typed
- [ ] A valid review shows a thank-you message once
- [ ] The review does **not** appear publicly (it is pending)
- [ ] The same email twice on one restaurant is refused
- [ ] Submitting more than 3 in a minute shows the friendly "too many requests" page

### Submit a restaurant `/submit-restaurant`
- [ ] Errors show for empty fields; a valid submission shows a thank-you
- [ ] It appears in **Admin → Submissions** as pending

### Contact `/contact`
- [ ] Errors show for empty fields; a valid message shows a thank-you
- [ ] It appears in **Admin → Messages** as unread

### Technical pages
- [ ] `/sitemap.xml` loads and lists published restaurants only
- [ ] `/robots.txt` loads (locally it says `Disallow: /`; on the live site it must allow search engines)
- [ ] A made-up address like `/zzz` shows the branded 404 page
- [ ] "View page source" on the homepage and a restaurant page shows a title, description, canonical link and JSON-LD

---

## Admin pages (all under `/admin`)

For every admin page on **phone**: the Menu button opens the sidebar and the table scrolls sideways inside its box, not the whole page.

### Sign in `/admin/login`
- [ ] Opening `/admin` or any admin page while signed out sends you to the sign-in page
- [ ] A wrong password shows "The email or password is not correct." and keeps your email
- [ ] The right email and password open the dashboard (or the page you first asked for)
- [ ] **Sign out** (top bar) returns to the sign-in page, and pressing Back does not show admin pages
- [ ] After 5 wrong tries in a minute you are blocked for a short time
- [ ] **Phone:** the sign-in form fits the screen and the keyboard does not hide the button

### Dashboard `/admin`
- [ ] Counts show; pending reviews, pending submissions and unread messages match the real numbers
- [ ] Sidebar highlights "Dashboard" in gold

### Restaurants `/admin/restaurants`
- [ ] List with search, filters and paging
- [ ] **Add:** missing required fields show errors; a valid form saves and shows a green success message
- [ ] **Edit:** values and ticked cuisines/amenities are pre-filled; saving changes them
- [ ] **Photos:** upload a cover and gallery images; a .pdf or a file over 3 MB is refused
- [ ] **Opening hours:** save and check they appear on the public page
- [ ] **Delete:** asks for confirmation; photos disappear from `storage/app/public`
- [ ] Switching a restaurant to Draft makes its public page a 404

### Cities, Cuisines, Amenities
- [ ] Add, edit, delete work with success messages
- [ ] A duplicate name is refused
- [ ] Deleting one that still has restaurants is blocked with a red error message

### Reviews `/admin/reviews`
- [ ] Tabs for pending, approved and rejected show the right reviews
- [ ] Approve makes the review appear on the restaurant page; reject and delete remove it

### Submissions `/admin/submissions`
- [ ] A visitor's suggestion shows; "create restaurant" pre-fills the form

### Messages `/admin/messages`
- [ ] Unread and read tabs work; the reply link opens your email program; delete works

---

## Final checks before going live
- [ ] `php artisan test` shows all tests passed
- [ ] `php artisan deploy:check` shows "All checks passed"
- [ ] `/admin` asks for the server password (the four `curl` tests in DEPLOYMENT.md all say 401)
- [ ] Real restaurants and photos are added; seed/sample data is gone from the live database
- [ ] `https://` shows the padlock; `http://` redirects to it
- [ ] A backup file was created and a test restore worked
