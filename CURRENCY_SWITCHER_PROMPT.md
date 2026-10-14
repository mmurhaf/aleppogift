# Currency switcher prompt

Add a storefront currency switcher to this PHP site, using the same pattern as a working shop whose prices are stored and charged in one base currency.

Discover the base currency from the existing price display, checkout, and payment code. Do not change the amount that is stored or charged. The switcher only changes what visitors see. Keep the base-currency amount visible next to a converted price, and keep checkout, invoices, and payment requests in the base currency.

Currency order in the switcher and in any rate table:

1. GCC, in this order: AED, SAR, KWD, BHD, QAR, OMR. Include the base currency here when it is one of these.
2. USD
3. EUR
4. The rest, in a stable useful order (GBP, then other majors).

Rates mean “1 unit of the base currency equals this many units of the target.” The base rate stays locked at 1. Use three decimals for KWD, BHD, and OMR, zero for JPY and KRW, and two for the others. Seed rates from a current public exchange-rate feed, with those values as code defaults so the site still works before anyone saves.

Store editable rates in the existing settings table if the project has one. Otherwise add one small settings table (key, value, updated time). Save a JSON map of code to rate, plus the date the rates were last saved. Do not overwrite saved rates on later page loads. An admin screen must list the currencies in the same order, edit each rate, show what 100 base units become, save, and restore the seeded reference rates. Link it from the admin navigation and from any existing read-only currency setting.

On the storefront:

- Put a compact dropdown in the header, grouped as Gulf countries, US dollar, Euro, then Other currencies.
- Remember the choice in a cookie (and session if one exists) and reload so server-rendered prices match.
- Default to the base currency when the cookie is missing or invalid.
- Centralize conversion in one include: catalog, selected currency, plain-text money format, and HTML price format.
- Replace customer-facing product, listing, cart, cart-preview, and checkout display prices with that helper. Leave admin order totals, PDFs, and payment payloads in the base currency.
- If JavaScript rewrites totals after a quantity change, make it convert from a data attribute that stores the base amount, not from the visible text.
- If the site has a shipping or currency reference table, drive it from the same catalog and date, ordered the same way. Sample costs are base shipping amounts multiplied by the rate.

Expose the selected currency and a small formatter to JavaScript for any price the page builds in the browser.

Afterward, verify in the browser: the default currency is the base currency, switching updates product prices and keeps the base amount visible, the shipping or reference table matches the saved rates and order, and the admin rate form saves without changing what checkout charges.
