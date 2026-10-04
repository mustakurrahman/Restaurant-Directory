# Putting RestaurantsDirectory.com on the internet

A step-by-step guide for a complete beginner. Take your time and do one step at a time. Every command is explained.

> ## ⚠️ The three things that matter most
>
> 1. **Protect `/admin` with a password on the server (Step 7).** The admin panel has no login of its own, by design. Until the server asks for a password, **anyone who finds the address can edit or delete your whole directory.**
> 2. **Set `APP_ENV=production` and `APP_DEBUG=false` in the live `.env` file (Step 4).** Otherwise Google is told to ignore your site, and visitors can see passwords when something breaks.
> 3. **Run `php artisan deploy:check` (Step 9).** It looks at your server and tells you in plain words what is still wrong, including whether `/admin` really asks for a password.

**What I have and have not tested.** The website, its 376 automated tests, the production settings (caching, `APP_ENV=production`, security headers) and the `deploy:check` command were all tested on the development computer. I could **not** test your actual hosting company or server, so the server parts (Steps 6 to 8) are careful examples you must adapt. If something behaves differently, `deploy:check` and the Troubleshooting table at the end are your friends.

---

## Words used in this guide

| Word | Meaning |
| --- | --- |
| **Server** | The computer on the internet that holds your website. Rented from a hosting company. |
| **VPS** | A server you control fully (you log in with a command-line tool called SSH). Needs more know-how, gives full control. |
| **Shared hosting / cPanel** | A server shared with other customers, managed through a control panel. Easier, more limited. |
| **SSH** | A way to type commands on the server from your own computer. |
| **Document root** | The one folder the web server shows to the world. For this site it **must** be the `public` folder, never the project folder itself. |
| **`.env` file** | The settings file with passwords. Different on each computer. Never put it in Git. |
| **Migration** | A script that creates or changes database tables. |
| **Cache** | Saved results so pages load faster. |
| **Proxy** | A gateway (for example Cloudflare) that sits between visitors and your server. |
| **HTTP Basic Auth** | The browser's built-in username-and-password pop-up. The *server* shows it, not the website. |

---

## Step 0: Before you start

You need:

- [ ] A **domain name** (RestaurantsDirectory.com) pointed at your server (the host tells you which address to use).
- [ ] A server with **PHP 8.3 or newer** and **MySQL 8**, plus **Composer** (PHP's package tool) and **Git**.
  - PHP extensions needed: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`.
- [ ] **SSH access** (strongly recommended). Without it, see "Shared hosting" below.
- [ ] On your **own computer**: the project, working, with all tests passing.

You do **not** need Node.js on the server. The styles and scripts are built on your computer (Step 1) and uploaded.

---

## Step 1: Get the code ready on your computer

In the project folder (`restaurant-directory`):

```powershell
php artisan test        # every test must pass
npm run build           # builds the styles and scripts into public/build
git status              # should say "nothing to commit"
git push                # make sure GitHub has the latest code
```

`npm run build` creates the folder `public/build`. It is **not** stored in Git (on purpose), so you will upload it yourself in Step 2.

Also make sure there is **no file named `public/hot`** (it only exists while `npm run dev` is running). If you see it, stop the dev server first.

---

## Step 2: Put the code on the server

Log in to the server with SSH, then:

```bash
cd /var/www
git clone https://github.com/mustakurrahman/Restaurant-Directory.git restaurant-directory
cd restaurant-directory
composer install --no-dev --optimize-autoloader
```

- `git clone` copies the project from GitHub.
- `composer install --no-dev --optimize-autoloader` downloads the PHP packages the site needs, **without** the developer tools, and makes them load faster.

Now upload the built styles from your computer. From your **own computer** (adjust the user and address):

```powershell
scp -r public/build youruser@your-server:/var/www/restaurant-directory/public/
```

(Or upload the `public/build` folder with an FTP program such as FileZilla.)

---

## Step 3: Create the database

On the server, open MySQL as an administrator and create a database **and a separate user** just for this site:

```sql
CREATE DATABASE restaurant_directory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'restaurant_app'@'localhost' IDENTIFIED BY 'a-long-random-password-you-invent';
GRANT ALL PRIVILEGES ON restaurant_directory.* TO 'restaurant_app'@'localhost';
FLUSH PRIVILEGES;
```

Write the password down somewhere safe (a password manager). You will paste it into `.env` next. Do not reuse the password of anything else.

---

## Step 4: Create the `.env` settings file

```bash
cp .env.production.example .env
nano .env          # or any editor you like
```

Fill in the lines marked `<<< CHANGE ME`. The important ones:

| Setting | Value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | Tells the site it is live. Also lets Google in (`robots.txt`). |
| `APP_DEBUG` | `false` | Hides technical error details from visitors. |
| `APP_URL` | `https://RestaurantsDirectory.com` | Used in the sitemap and links. Must start with `https`. |
| `DB_USERNAME`, `DB_PASSWORD` | From Step 3 | |
| `SESSION_SECURE_COOKIE` | `true` | Cookies only over https. |
| `TRUSTED_PROXIES` | empty, unless you use a proxy | See "Behind a proxy or Cloudflare". |

Keep `APP_KEY=` empty for now; the next step creates it.

**Never** commit this `.env` to Git, email it, or paste it into a chat. It contains your database password.

---

## Step 5: The first-time commands

Still in the project folder on the server:

```bash
php artisan key:generate --force     # creates the secret key in .env (only the first time, never again!)
php artisan migrate --force          # creates all the database tables
php artisan storage:link             # makes uploaded photos reachable from the web
php artisan optimize                 # saves config, routes and views for speed
```

- `--force` is needed because on a live site Laravel asks "are you sure?".
- **Never run `key:generate` again later**: it would log everyone out and break saved data.

**Permissions.** The web server must be allowed to write to two folders. On Ubuntu/Debian with the usual `www-data` user:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

> **Do not** run `php artisan db:seed` or `migrate:fresh` on the live site. The seeder fills the database with *made-up sample restaurants*. `migrate:fresh` would delete everything. On the live site both are blocked or confirmed for your safety (`migrate:fresh`, `migrate:refresh`, `migrate:reset` and `db:wipe` are refused in production).

---

## Step 6: Point the web server at the `public` folder

The document root **must** be `/var/www/restaurant-directory/public`. Pointing it at the project folder would expose `.env` and your code to the world.

### Option A: Nginx

Create `/etc/nginx/sites-available/restaurantsdirectory.com`:

```nginx
server {
    listen 80;
    server_name restaurantsdirectory.com www.restaurantsdirectory.com;

    root /var/www/restaurant-directory/public;
    index index.php;
    charset utf-8;

    client_max_body_size 12M;          # photo uploads: up to 3 MB each, several at once

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # --- The admin panel: password protected (Step 7), handled right here so the password cannot be skipped ---
    location ^~ /admin {
        auth_basic           "Restricted area";
        auth_basic_user_file /etc/nginx/.htpasswd-restaurants;

        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param SCRIPT_NAME     /index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # --- All other PHP goes through index.php. "internal" means nobody can type /index.php/admin in the browser
    #     to walk around the password rule above. ---
    location ~ ^/index\.php(/|$) {
        internal;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ { return 404; }                 # no other PHP file may be run directly
    location ~ /\.(?!well-known).*  { deny all; }     # never serve .env, .git and other hidden files
}
```

Notes:

- Do **not** add a `location = /robots.txt` block (many Laravel examples have one). The site generates `robots.txt` itself, and that block would break it.
- The PHP socket name (`php8.3-fpm.sock`) depends on your PHP version. Check with `ls /run/php/`.
- Enable and test:
  ```bash
  sudo ln -s /etc/nginx/sites-available/restaurantsdirectory.com /etc/nginx/sites-enabled/
  sudo nginx -t && sudo systemctl reload nginx
  ```

### Option B: Apache

Set `DocumentRoot /var/www/restaurant-directory/public`, enable `mod_rewrite`, and allow `.htaccess`:

```apache
<VirtualHost *:80>
    ServerName restaurantsdirectory.com
    ServerAlias www.restaurantsdirectory.com
    DocumentRoot /var/www/restaurant-directory/public

    <Directory /var/www/restaurant-directory/public>
        AllowOverride All
        Require all granted
    </Directory>

    # --- The admin panel: password protected (Step 7) ---
    <LocationMatch "^/+(index\.php/+)?admin">
        AuthType Basic
        AuthName "Restricted area"
        AuthUserFile /etc/apache2/.htpasswd-restaurants
        Require valid-user
    </LocationMatch>
</VirtualHost>
```

The project already includes Laravel's `public/.htaccess`, which does the URL rewriting.

### Shared hosting (cPanel and similar)

- Set the domain's **document root** to the project's `public` folder (cPanel: *Domains → Manage → Document Root*). If your host does not allow this, ask their support; do not put the project inside `public_html`.
- No SSH? Run `composer install --no-dev --optimize-autoloader` and `npm run build` on your own computer and upload the whole project including `vendor/` and `public/build/` (never your local `.env`). Run the migrations through the host's terminal if it has one, or ask support.
- For the `/admin` password, use the `.htaccess` method in Step 7.

---

## Step 7: Protect `/admin` with a password

Create the password file. On the server (you will be asked to type the password twice):

```bash
sudo apt install apache2-utils          # provides the "htpasswd" tool (skip if already installed)
sudo htpasswd -c /etc/nginx/.htpasswd-restaurants owner      # for Nginx
# or:
sudo htpasswd -c /etc/apache2/.htpasswd-restaurants owner    # for Apache
```

- `owner` is the username; choose what you like.
- `-c` *creates* the file. To add another person later, run the same command **without** `-c`.
- Use a **long, unique password**. Anyone who guesses it controls your site.

The matching rules are already in the Nginx and Apache examples in Step 6. Reload the server after creating the file.

**Shared hosting without server access?** Put this in `public/.htaccess`, near the top, and create the password file with your host's *Directory Privacy* tool or `htpasswd` (use the full server path to the file):

```apache
<If "%{REQUEST_URI} =~ m#^/+(index\.php/+)?admin#">
    AuthType Basic
    AuthName "Restricted area"
    AuthUserFile /home/youraccount/.htpasswd-restaurants
    Require valid-user
</If>
```

### Test that it really works

From any computer (not logged in), run these. **Each must say `401`** (or `404` for the last two):

```bash
curl -I https://restaurantsdirectory.com/admin                  # expect: HTTP/2 401
curl -I https://restaurantsdirectory.com/admin/restaurants      # expect: HTTP/2 401
curl -I https://restaurantsdirectory.com/%61dmin                # expect: 401 (an encoded way of typing /admin)
curl -I https://restaurantsdirectory.com/index.php/admin        # expect: 401 or 404, never 200
```

If any of them says `200`, the password can be skipped. **Do not go live until all are fixed.** Then open `/admin` in a browser: you should get the password pop-up.

---

## Step 8: HTTPS (the padlock)

Without https, passwords and the admin password travel in the clear, browsers show "Not secure", and Google ranks you lower. Free certificates come from **Let's Encrypt**:

```bash
sudo apt install certbot python3-certbot-nginx     # for Apache: python3-certbot-apache
sudo certbot --nginx -d restaurantsdirectory.com -d www.restaurantsdirectory.com
```

Certbot edits your server settings, adds the certificate, redirects `http://` to `https://`, and renews automatically. Afterwards run `php artisan optimize:clear && php artisan optimize` and confirm `APP_URL` in `.env` starts with `https://`.

Once https works, the site automatically tells browsers to always use https (HSTS).

### Behind a proxy or Cloudflare

Most simple servers can skip this. **If a proxy sits in front of your server** (Cloudflare, a load balancer, or a host that does https for you), every visitor appears to come from the proxy's address. Then:

- the spam limits (reviews, contact, submissions) would treat **all visitors as one person**, so after a few messages everyone is blocked; and
- the site may think it is on plain http.

Fix: in `.env` set `TRUSTED_PROXIES` to the proxy's address(es), for example `TRUSTED_PROXIES=10.0.0.1,10.0.0.2`. Use `TRUSTED_PROXIES=*` **only** if your server can be reached *only* through the proxy (for example you allow only Cloudflare's addresses in the firewall). With an empty value, the site trusts nothing, which is the safe choice for a server visitors reach directly. Then run `php artisan optimize:clear && php artisan optimize`.

---

## Step 9: Run the deployment check

```bash
php artisan deploy:check
```

It prints **PASS**, **WARN** or **FAIL** for each item, with the exact fix next to every failure. It checks, among other things:

- `APP_ENV`, `APP_DEBUG`, `APP_KEY`, an `https` `APP_URL`, secure cookies
- the database connection and that every migration has run
- that `storage`, `public/storage` and `public/build` are in place and there is no leftover `public/hot`
- **from the outside: that `/admin` really asks for a password**, that `robots.txt` lets search engines in, and that `sitemap.xml` loads

Fix every **FAIL**, then run it again until it says **"All checks passed"**. Read each **WARN** and decide. (If you run it from a computer that cannot reach the site, add `--skip-http`.)

---

## Step 10: Add your real content

Open `https://restaurantsdirectory.com/admin` (enter the password), then in this order:

1. **Cities**, **Cuisines**, **Amenities**: the lists restaurants choose from.
2. **Restaurants**: add each one, then upload a cover photo and gallery photos, and set the opening hours. A restaurant starts as **Draft** (invisible). Change it to **Published** when it is ready.

Photos matter: Google prefers restaurant pages with a real image.

Check the public site as a visitor: the homepage, a city page, a restaurant page, the contact form and the submit form.

---

## Step 11: Tell Google

1. Create a free account at **Google Search Console** and add your domain.
2. Open **Sitemaps** and submit `sitemap.xml`.
3. Use **URL inspection → Request indexing** for the homepage.
4. Test a restaurant page in Google's **Rich Results Test** to confirm it reads the `Restaurant` data.

Indexing takes days to weeks. That is normal.

---

## Updating the site later

When you have new code:

```bash
cd /var/www/restaurant-directory
php artisan down --retry=60          # shows the friendly "We'll be right back" page
git pull
composer install --no-dev --optimize-autoloader
# upload a new public/build from your computer if you changed styles or scripts
php artisan migrate --force          # only changes the database if there is something new
php artisan optimize:clear
php artisan optimize
php artisan up
php artisan deploy:check
```

If anything goes wrong after an update: `git log --oneline` shows previous versions; `git checkout <earlier-version>` goes back (restore the database from a backup if a migration changed it).

---

## Backups (do this before you need it)

You have two things to protect: the **database** and the **uploaded photos**.

```bash
# Database (a file you can restore from)
mysqldump -u restaurant_app -p restaurant_directory | gzip > /home/youruser/backups/db-$(date +%F).sql.gz

# Photos
tar -czf /home/youruser/backups/photos-$(date +%F).tar.gz -C /var/www/restaurant-directory/storage/app public
```

Automate with `crontab -e` (daily at 3 a.m.), and **copy backups off the server** (another computer or cloud storage). A backup stored only on the same server is lost with it.

To restore a database: `gunzip < db-2026-01-01.sql.gz | mysql -u restaurant_app -p restaurant_directory`.

---

## Troubleshooting

| What you see | Likely cause | Fix |
| --- | --- | --- |
| **500 error** on every page | A setting or permission | Read the last lines of `storage/logs/laravel-*.log`. Run `php artisan deploy:check`. Check Step 5 permissions. |
| Page appears **without styling** | `public/build` missing, or `public/hot` exists | Upload `public/build`. Delete `public/hot`. |
| **Uploaded photos missing** | Storage link missing | `php artisan storage:link` |
| Forms say **"Page expired" (419)** | Cookies blocked, `APP_URL` wrong, or https not working with `SESSION_SECURE_COOKIE=true` | Fix https (Step 8) and `APP_URL`. |
| After a few form uses **everyone is blocked (429)** | Behind a proxy without `TRUSTED_PROXIES` | See "Behind a proxy or Cloudflare". |
| **Google ignores the site** | `APP_ENV` is not `production` (robots.txt says `Disallow: /`) | Fix `.env`, then `php artisan optimize:clear && php artisan optimize`. |
| **Changes in `.env` do nothing** | Settings are cached | `php artisan optimize:clear && php artisan optimize` |
| Counts in the filter panel look **a few minutes old** | They are cached briefly | They clear on every edit; or `php artisan cache:clear`. |
| `/admin` **does not ask for a password** | Step 7 incomplete | Do not stay live. Fix and re-test with the `curl` lines. |
| **"Class not found"** after an update | Packages not installed | `composer install --no-dev --optimize-autoloader` |

---

## What is already built in for safety

- Spam protection on every public form: a hidden trap field, a rate limit per visitor, and a security token. New reviews, suggestions and messages are never public until you approve them.
- Uploads are limited to JPG, PNG and WebP up to 3 MB, and are deleted from disk when their record is deleted.
- Security headers on every page (no framing by other sites, no guessing of file types, minimal referrer information, https enforcement over https).
- The commands that erase the database are refused in production.
- `/admin`, error pages and form results are kept out of Google. The local-only preview pages do not exist on the live site.

## Your responsibilities

- Keep the **admin password** long and private; change it if someone who knew it leaves.
- Make and test **backups**.
- Keep the server and PHP **updated**, and run `composer update` now and then on your computer (test, then deploy) to receive security fixes.
- Watch `storage/logs` occasionally for errors.

Ideas that are deliberately **not** in version 1 are collected in [VERSION2.md](VERSION2.md).
