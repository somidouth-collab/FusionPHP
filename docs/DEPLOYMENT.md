# Deploy FusionPHP

## Prepare the release

1. Deploy the project outside the web server’s default document root. Point the site’s document root to the release’s `public/` directory. Keep `app/`, `fusionphp/`, `plugins/`, `storage/`, and `.env` outside the public tree.
2. Provide PHP with sessions enabled. Enable the matching PDO driver in both CLI PHP and the web PHP process when using a database. PHP CLI and PHP-FPM can load different configuration files.
3. Configure the production environment using `.env.example` as a reference. Process environment values override the file. Keep secrets in a private environment file or a deployment secret store.
4. Give the PHP worker write access to `storage/cache/` and `storage/logs/`, and to its configured session store. When using SQLite, give the processes running web requests and migrations the appropriate access to the database and its parent directory. Keep application code read-only for the web process.
5. Start with debug disabled. Serve HTTPS at the public endpoint. Use the HTTPS origin for `APP_URL` so FusionPHP sets secure session cookies.

Example for a root installation:

```dotenv
APP_URL=https://fusion.example.com
APP_MODE=both
APP_BASE_PATH=
FUSIONPHP_DEBUG=false
```

Use `APP_MODE=both` for web and API routes, `web` for web routes only, or `api` for API routes only. For a mount path such as `/fusion`, set `APP_BASE_PATH=/fusion`, keep `APP_URL` as the origin, and configure the server to serve assets and forward the original prefixed URI to the entry point.

## Nginx with PHP-FPM

Adapt the document root and PHP-FPM socket to your server. This example shows routing inside a site configuration. Configure TLS for the public server or terminate it at your HTTPS proxy.

```nginx
server {
    server_name fusion.example.com;
    root /srv/fusionphp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ \.php(?:/|$) {
        return 404;
    }
}
```

The exact-match FastCGI location handles clean URLs. The catch-all blocks execution of other PHP paths. [Nginx request processing](https://nginx.org/en/docs/http/request_processing.html)

## Apache with PHP configured

Use this inside the site’s virtual host. Configure your PHP handler for PHP files, and enable the modules your PHP configuration requires.

```apacheconf
DocumentRoot "/srv/fusionphp/public"

<Directory "/srv/fusionphp/public">
    Require all granted
    Options -Indexes
    DirectoryIndex index.php
    FallbackResource /index.php
    CGIPassAuth On
</Directory>
```

`FallbackResource` serves existing assets directly and forwards unknown paths to FusionPHP. `CGIPassAuth On` makes the Authorization header available to CGI-based PHP handlers for the Admin Panel. [Apache front-controller routing](https://httpd.apache.org/docs/2.4/rewrite/avoid.html)

## Release and verify

1. Back up the database before schema changes. For SQLite or PostgreSQL, check the deployment’s configured database and run `php fusion migrate:status`, then `php fusion migrate`. For MySQL, apply schema changes with your database tooling.
2. Preserve persistent data and deployment-specific credentials across releases. If the Admin Panel is installed, preserve its installed module and password hash. Give the PHP worker read access to the hash if it runs as a different account from the installer.
3. Request the home page, `/framework`, and `/assets/css/fusionphpstyle.css`. In `both` mode, request `/api` and verify it returns JSON. If the Admin Panel is installed, verify that an anonymous request to `/admin` returns HTTP 401 and that valid credentials open the dashboard.
4. Review the application and web server error logs. A 404 on every clean route usually points to the document root or front-controller routing. An Admin Panel 401 with known-good credentials can indicate that Authorization is not reaching PHP. HTTP 503 on the panel can indicate that the password hash is unreadable.
