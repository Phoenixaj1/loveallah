# Love Allah (loveallah.app)

A sacred ritual app that draws each Muslim closer to Allah swt. The masjid is the pathway. Advertising is the consequence — all revenue flows through YourNiyyah to partner charities, Islamic projects, and the masjids that join.

## What's inside

```
loveallah/
├── plugins/loveallah/          Single WordPress plugin (all backend)
│   ├── loveallah.php           Entry · lifecycle hooks · cron
│   ├── inc/                    PHP classes
│   │   ├── class-la-db.php           DB schema + migrations (v5)
│   │   ├── class-la-algorithm.php    Feed algorithm (recency + affinity + dhikr/signup interleaving)
│   │   ├── class-la-youtube.php      yt-dlp ingestion · portrait-only Shorts
│   │   ├── class-la-feed-render.php  Shared card rendering (PHP + AJAX)
│   │   ├── class-la-api.php          REST API · /wp-json/loveallah/v1/*
│   │   ├── class-la-admin.php        WP-Admin UI (Dashboard, CRUD, Settings)
│   │   ├── class-la-events.php       Per-masjid events
│   │   ├── class-la-cli.php          WP-CLI commands
│   │   └── class-la-caps.php         Capabilities + custom role
│   └── assets/                 CSS + JS
└── theme/loveallah-starter/    Theme: feed + filtered category + Connect + Masjid pages
```

## Tabs / pages

| Slug          | Purpose                                                              |
|---------------|----------------------------------------------------------------------|
| `/`           | Main feed — interleaved dhikr cards + Shorts + signup interstitials  |
| `/dhikr`      | Pre-filtered: dhikr recitations only                                 |
| `/nasheed`    | Pre-filtered: nasheeds only                                          |
| `/mindfulness`| Pre-filtered: ambient/focus Islamic content                          |
| `/connect`    | Patron tiers · verified badges · people directory                    |
| `/masjid`     | Local masjid hub (prayer times · events · services · announcements)  |
| `/wp-admin`   | Love Allah admin menu (Dashboard, Scholars, Mosques, Events…)        |

## Local development

```bash
cd C:\Users\user\Documents\loveallah
docker compose up -d
```

| Service     | URL                       | Credentials       |
|-------------|---------------------------|-------------------|
| WordPress   | http://localhost:8092     | admin / admin     |
| phpMyAdmin  | http://localhost:8093     | wpuser / wppass   |

MySQL port: `3309` (host) → `3306` (container).

### Install yt-dlp in the container (for portrait Shorts sync)

```bash
docker exec loveallah-wordpress-1 bash -c \
  "apt-get update -qq && apt-get install -y -qq python3 ca-certificates \
   && curl -sL https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp \
        -o /usr/local/bin/yt-dlp \
   && chmod a+rx /usr/local/bin/yt-dlp"
```

## Deploying

Auto-deploys on push to `main` via GitHub Actions (see `.github/workflows/deploy.yml`). The workflow rsyncs the plugin + theme to the Cloudways server and flushes WP, Varnish and Cloudflare caches.

### Required GitHub Secrets
- `SSH_PRIVATE_KEY` — base64-encoded private key (`cat key | base64 -w0`)
- `SSH_HOST`, `SSH_USER`, `SSH_PORT`, `WP_PATH`
- `CF_ZONE_ID`, `CF_API_TOKEN` (optional — Cloudflare cache purge)

### Manual deploy
Copy `.env.deploy.example` to `.env.deploy`, fill in values, then:
```bash
bash deploy.sh
```

## WP-CLI

```bash
wp loveallah stats           # platform metrics
wp loveallah sync            # YouTube Shorts ingest
wp loveallah scholar list    # list scholars
wp loveallah seed-events     # seed sample events for default mosque
```

## License

MIT — see `LICENSE`.
