# DIU Faculty Directory (Next.js)

A faculty directory web app for Daffodil International University, built with the latest **Next.js 16 (App Router)**, **React 19**, and **Tailwind CSS v4**.

## Requirements

- **Node.js >= 20.9.0** (developed on Node 24 LTS via `nvm`).
- npm

## Getting started

```bash
nvm use 24        # or any Node >= 20.9
npm install
npm run dev       # http://localhost:3000
```

## Scripts

- `npm run dev` – start the dev server
- `npm run build` – production build
- `npm run start` – serve the production build
- `npm run lint` – run ESLint

## Notes

- The app fetches faculty/department/teacher data from a backend proxied via
  `next.config.js` rewrites (`/api/*` → `http://localhost:8000/api/*`).
- Tailwind v4 is configured through `tailwind.config.mjs` and imported in
  `src/index.css` via `@config`.
