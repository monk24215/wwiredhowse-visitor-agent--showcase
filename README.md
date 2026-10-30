# Portfolio

A self-contained, droppable project portfolio. Static HTML/CSS/JS. No build step. No backend.

Drop the entire `portfolio/` folder into any web-accessible path on any of your sites — root, subfolder, subdomain, separate static host. All paths inside are relative.

## Layout

```
portfolio/
├── index.html              ← summary grid (this is the landing page)
├── projects.json           ← single source of truth — drives the grid
├── assets/
│   ├── portfolio.css       ← shared design system
│   └── portfolio.js        ← grid renderer + filter
└── projects/
    ├── _template.html      ← duplicate this for new projects
    └── favicon-forge.html  ← example: a complete project page
```

## Adding a project

1. **Add an entry to `projects.json`** at the top of the array (newest first):

   ```json
   {
     "slug": "your-project-slug",
     "title": "Project Name",
     "tagline": "One sentence describing what it is.",
     "year": 2026,
     "status": "live",
     "stack": ["Tech 1", "Tech 2", "Tech 3", "Tech 4", "Tech 5"],
     "live_url": "https://example.com",
     "repo_url": "https://github.com/...",
     "page": "projects/your-project-slug.html"
   }
   ```

   Fields:
   - `slug` — URL-friendly identifier, also used as filename
   - `status` — `live`, `in-progress`, or `archived` (drives filter chip + colored dot)
   - `stack` — first 5 entries shown on the card; full list lives in the project page
   - `page` — relative path from `index.html` to the full project HTML

2. **Duplicate `projects/_template.html` → `projects/{slug}.html`** and fill in the placeholders (everything wrapped in `{...}`).

3. Done. The index regenerates the grid from `projects.json` on next load.

## Customizing the look

All colors, spacing, and typography live as CSS variables at the top of `assets/portfolio.css`. Change once, propagates everywhere.

## Deploying to multiple sites

Three options, ordered by maintenance cost:

1. **Manual copy** — duplicate the folder into each site. Simple, but updates have to be applied N times.
2. **Git submodule** — keep the portfolio in its own repo, add as a submodule to each site. One source of truth, `git submodule update` to pull changes.
3. **Central host + cross-site link** — host once at e.g. `portfolio.wiredhowse.com` and link from each site. Easiest to maintain, but doesn't live inside each site.

## Notes

- Index requires JS (it reads `projects.json`). Project pages are pure HTML — they work with JS disabled.
- `projects.json` must be served from the same origin as `index.html` (same-origin fetch).
- Cards link to whatever `page` says — could be a local HTML file, an external URL, anything.
