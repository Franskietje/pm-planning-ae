# Cloudflare Worker Deploy (GitHub Pages + Worker)

## 1) Install tools

```powershell
npm install -g wrangler
wrangler login
```

## 2) Create worker project files

Copy `wrangler.toml.example` to `wrangler.toml` and adjust values if needed.

## 3) Set secrets (if you do not want vars in wrangler.toml)

```powershell
wrangler secret put FM_HOST
wrangler secret put FM_DB
wrangler secret put FM_VERSION
```

## 4) Deploy

```powershell
wrangler deploy
```

## 5) Configure CORS origin

Set `ALLOWED_ORIGINS` to your exact GitHub Pages origin.

- User/Org Pages: `https://franskietje.github.io`
- Project Pages: `https://franskietje.github.io/pm-planning-ae` (still same origin)

## 6) Frontend config

`js/app-config.js` points to:

`https://withered-feather-3456.sd-5bd.workers.dev`

## 7) Verify endpoints

- `GET /ping`
- `POST /auth/login`
- `GET /find/planning?from=2026-03-01&to=2026-03-31`
- `POST /auth/logout`

## 8) GitHub Pages entry

Use `index.html` -> redirects to `login.html`.
