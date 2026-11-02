# SzalonPRO demo customer journey — implementation and QA

Date: 2026-11-02. [Review PR #1](https://github.com/zoltan2561/Szalon_Pro/pull/1).

Implementation revision: `d1d0ceaace95cc4396ea9b17b23836d8ebf9545c`.
Base: `da3dd3bc8b033e37f532f86556eee5121166a1b1`.
Branch: `feat/demo-customer-journey-20261102`.

## Changes

- Demo order/quote CTAs lead to the central SzoftLab contact form with `tipus=order`, `termek=szalonpro`, `csomag=monthly`, the actual demo host as `utm_source`, `utm_medium=demo`, and the placement as `utm_campaign`. Question CTAs use `tipus=question`. Blade escapes the generated URL once.
- “Vendégkezelés megnyitása” uses the existing password-free demo login with an allowlisted destination. Reader mutations remain forbidden; anonymous public booking remains available.
- The price remains 4,900 HUF/month with no setup fee. Price and FAQ show the exact VAT and 30-day termination wording. The additional explanation follows the central ASZF 8.1–8.2: termination takes effect at the end of the month in which the notice period expires.
- Three startup steps, five visible FAQ answers with matching FAQPage data, central product/legal footer links, and a separately labeled demo privacy sample are present. The mobile demo bottom bar no longer overlays controls; footer links wrap with spacing.
- Only the primary `https://szalon.pro/` demo landing is indexable. Demo variants and private/booking routes remain noindex with the primary demo canonical. Demo sitemap/robots and SoftwareApplication/monthly HUF Offer metadata are present. Customer pages receive no demo canonical, schema, or central marketing footer.
- Optional null demo URLs no longer break clean-environment customer routing/provisioning.

## Verification

| Check | Result |
| --- | --- |
| `php artisan test --compact` | 433 passed; 4,288 assertions; zero failures/errors |
| `php vendor/bin/pint --dirty --format agent` | Passed |
| `npm run build` | Passed; optional `fontaine` optimized-fallback notice only |
| Frontend premium `audit_project.py --mode strict` | Zero findings |
| `git diff --check` | Passed |
| Independent review | No concrete regression; separately checked marketing, reader restrictions, tenancy, and Google paths |

Tests ran with the isolated worktree and PHPUnit's SQLite fixture, array mailer/cache/session, and a process-level application key. No production `.env` or database was read or copied. The browser server used only the generated local SQLite fixture, `MAIL_MAILER=array`, disabled billing sending, no configured Google/provider credentials, and no scheduled outbound workers.

Real-browser checks at 390×844 and 1280×900 confirmed anonymous password-free guest-management entry, the pricing/FAQ/footer, zero horizontal page overflow, no demo sticky overlay, and zero console errors. Native FAQ interaction also worked with keyboard and reduced motion. An anonymous synthetic booking completed at `/foglalas/kesz`; an independent read of the isolated SQLite database confirmed exactly one booking for `b1-synthetic@example.test`, named “Minta Demo Vendeg”, on 2026-11-03. This was a local fixture write only. Automated reader tests retain POST=403 and verify the allowlist rejects an external destination.

Google direction is established by `app/Services/GoogleCalendarSync.php`: `exportBookings()` sends bookings to the connected Google calendar and `importBusy()` stores external busy intervals. `app/Services/Availability.php` excludes those intervals from offered slots. This supports the FAQ copy; it is not evidence of a live OAuth/provider connection.

## Committed screenshots

These are the original browser PNGs copied without editing from the local QA output. The UI implementation revision is the SHA above; the calendar capture predates the final commit but no calendar-rendering code changed afterward.

| Evidence | 390 px | 1280 px |
| --- | --- | --- |
| Anonymous landing | [PNG](demo-customer-journey-20261102/home-390-anonymous.png) | [PNG](demo-customer-journey-20261102/home-1280-anonymous.png) |
| Pricing and legal copy | [PNG](demo-customer-journey-20261102/pricing-390.png) | [PNG](demo-customer-journey-20261102/pricing-1280.png) |
| Password-free guest-management destination | [PNG](demo-customer-journey-20261102/guest-entry-390.png) | [PNG](demo-customer-journey-20261102/guest-entry-1280.png) |
| Spaced legal footer | [PNG](demo-customer-journey-20261102/footer-390-final.png) | — |
| Expanded FAQ | [PNG](demo-customer-journey-20261102/faq-open-390.png) | — |
| Anonymous booking success | [PNG](demo-customer-journey-20261102/booking-success-390.png) | — |

[Original local calendar source PNG, 1280×1000](demo-customer-journey-20261102/calendar-day-source.png), also supplied for the central marketing gallery.

Calendar URL: `http://127.0.0.1:8796/admin/naptar?date=2026-11-02&view=day`.
This is a genuine **local, synthetic, read-only demo capture**, not the live szalon.pro database. Fixture profile: `salon`, salon name “QA · Fodrászat”, three staff (Fodor Annamária, Kiss Boglárka, Nagy Eszter), and 27 synthetic November 2 bookings. Reader masking displays “Minta vendég #ID”. The screenshot is scrolled to show the calendar and bookings rather than only its controls.

Fixture creation used the existing `LocalDemoProfilesTest` with `SALON_QA_OUTPUT=output/playwright/b1` and `php artisan test --compact tests/Feature/LocalDemoProfilesTest.php --filter=test_each_profile_has_working_admin` (3 passed, 60 assertions), then `salon:demo-user` against that generated salon SQLite database. Local fixture path: `storage/framework/qa2-salon-faeb264f-d7ee-4e2f-b75f-eac170b5c249.sqlite`; manifest: ignored `output/playwright/b1/profiles.json`. The later anonymous November 3 booking does not alter the captured November 2 calendar.

## Open items and operating boundary

- No unresolved placeholders. `{{GOOGLE_NAPTAR_IRANY}}` is unnecessary because the direction is verified from code.
- Tracked-source search found no “Teszt Zoli”; existing tracked installation seeds already use neutral sample names. Live-only sample data was not renamed or queried.
- Production deployment, cache propagation, live Google/SMTP/provider behavior, and live booking persistence have not been tested by this work.
- The feature branch and this PR are for review only. Main was unchanged at the verified base SHA; no merge, deployment, DNS change, or live database mutation occurred.
