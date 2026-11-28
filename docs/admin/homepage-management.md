# Customer homepage management

The customer homepage is configured from the Next.js admin under Marketing →
Homepage layout and Homepage banners. Laravel provides the API and persistence;
the Laravel web interface is not part of this workflow.

The layout editor controls the visibility and order of the six customer rows,
their English and Bangla headings, the selected titles and order in Trending
Now, and the premium promotion card. The banner editor continues to manage
hero slides. Other catalog rows are populated from the existing content and
live TV APIs.

The admin endpoints are protected by `marketing.banners.manage`:

- `GET /api/admin/marketing/homepage`
- `GET /api/admin/marketing/homepage/catalog?search=...`
- `PUT /api/admin/marketing/homepage`
- Existing banner endpoints: `GET|POST /api/admin/marketing/banners` and
  `PUT /api/admin/marketing/banners/{banner}`

The customer web app reads the layout and its section payloads from
`GET /api/v3/homepage-config`. Each configured section includes its `items`,
`more_href`, and `ranked` presentation values, so the customer UI does not map
section keys to separate static lists. Catalog items come from the existing
Laravel content and live TV sources. The layout JSON is stored in the additive
`web_homepage_configs` table. Apply the Laravel migration before using the new
editor. The web DAL revalidates homepage data at most once per minute.
