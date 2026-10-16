# SzalonPRO Atelier design QA

Date: 2026-10-16. Scope: the user-selected option 1, Atelier editorial, applied to the existing configurable product and all three saved industry presets.

## Visual truth and evidence

- Selected source: `output/design/reference-editorial.png`, normalized from `exec-2ab94bb6-9d68-4b56-b2a2-73a82cf1b6f4.png`. The user explicitly answered 1 to the original three-option question.
- Figma foundations: https://www.figma.com/design/KpvtZCXgsAV78AENAzP2qZ?node-id=3-137 . The hair palette variables and visible hexadecimal labels now match the cream/burgundy implementation. The footer records the selected direction. The generated asset board remains at node 6:11.
- Local application: http://127.0.0.1:8095/ (hair), :8096/ (barber), :8097/ (nails).
- Current desktop implementation: `output/design/editorial-hair-desktop-final.jpg`.
- Full comparison, opened together: `output/design/editorial-comparison-final.png`.
- Focused headline, supporting copy and CTA comparison, opened together: `output/design/editorial-focus-final.png`.
- Other desktop profiles: `output/design/editorial-barber-desktop.jpg`, `output/design/editorial-nails-desktop.jpg`.
- Three mobile profiles, opened together: `output/design/editorial-mobile-profiles.jpg`.
- Additional mobile evidence: `editorial-hair-mobile-320.jpg`, `editorial-barber-mobile-320.jpg`, `editorial-nails-mobile-320.jpg`, `editorial-barber-services-320.jpg`, `editorial-booking-mobile.jpg`, all in `output/design/`.

## Viewport, density and state

Source: 1487 x 1058 pixels, normalized proportionally to the intended 1440 x 1024 desktop frame. Actual desktop CSS viewport: 1440 x 1024. Browser content screenshot: 1426 x 1014, normalized to the same comparison frame; no browser chrome or invented device frame was compared. Full comparison has a 26 px label strip.

Mobile CSS viewports: 390 x 844 and 320 x 704. The browser's zoom required compensated per-tab dimensions of 429 x 928 and 352 x 774. Saved content images are 376 x 813 and 306 x 673. Widths, absence of horizontal overflow and nonzero photo dimensions were read from the rendered DOM for all three profiles at both sizes. Temporary emulation and viewport overrides were cleared after testing.

State: published synthetic local demos, public homepage at scroll zero; native mobile menu opened; mobile services anchor; booking form with 11 available slots. Owner settings were used to apply each saved preset. The hair headline was saved with an editable line break. Existing catalogs, guests, bookings and custom media were preserved. The 32 px demo disclosure, SP logo, original generated interiors and actual service content intentionally differ from the illustrative concept. Barber and nails intentionally retain their industry palettes.

## Findings and comparison history

1. [P1, fixed] The previous implementation used option 3. All three presets now default to the selected split editorial layout. Hair uses a cream/burgundy palette, a large two-line serif headline, inline desktop actions and the curved-edge photo. The immersive alternative remains selectable. Verified by persisted owner settings, public DOM, feature tests and current captures.
2. [P2, fixed] First comparison showed undersized CTA text and brand type, plus muted supporting copy. Increased the desktop hero CTA to 20 px, secondary action to 18 px, wordmark to 32 px and supporting copy to the ink token. Post-fix comparison: `editorial-focus-final.png` and `editorial-comparison-final.png`.
3. [P1, fixed] Mobile flex layout inherited centered cross-axis alignment; the picture width collapsed to zero. Explicitly sized the picture to 100%. Post-fix DOM picture widths: 376.36 px at CSS 390 and 306.36 px at CSS 320 for every industry profile. Actual generated photos are visible in the mobile composite.
4. [P2, fixed] Hiding a desktop line break joined two supporting sentences on mobile. Added a literal separating space. Post-fix DOM confirms separated sentences at 320 px; evidence: `editorial-barber-services-320.jpg`.
5. Browser control temporarily timed out. The user confirmed the extension connection; testing resumed through the same Chrome browser and completed. Earlier stale/intermediate resize captures were discarded from final evidence.

## Required fidelity surfaces

- Typography: self-hosted Playfair Display and DM Sans remain real text. Source hierarchy, two-line hair headline, serif editorial character and large CTAs are retained. Line breaks are editable in the owner form. Barber uses its saved sans family. No clipped display text was observed at tested sizes.
- Layout rhythm: source-like split proportions, curved photo edge, editorial margins and flat service separators on desktop. Mobile stacks copy and the actual photo, uses a native menu and persistent booking action; all three profiles have no horizontal overflow at 320/390 px.
- Colors/tokens: cream/burgundy hair palette matches the selected direction; barber copper/dark and nails mauve/powder remain deliberate variants. Automated tests verify every preset's body/muted text contrast >= 4.5 and automatic button foreground contrast. Figma aliases remain bound to updated primitives.
- Image quality: original generated interiors, optimized WebP sources and transparent SP logo are integrated. Photos are sharp and correctly cropped. No placeholder or code-drawn asset replaces the supplied generated artwork.
- Copy/content: the selected hair headline and short introduction are used. Service names, durations and prices remain persisted business data. Synthetic demo disclosures and image captions remain visible. The mobile sentence-spacing defect is fixed.

## Validation and limits

- PHPUnit: 36 tests, 255 assertions passed after preset and form changes. Branding-specific suite: 7 tests, 110 assertions passed.
- Pint, Blade cache, route cache and whitespace checks passed.
- Browser: owner applies presets; hair multiline headline saved; public reads back the selected layout; mobile menu opens; services anchor works; mobile booking form loads 11 available slots without overflow. Captured console error list was empty.
- Booking transactions were covered by the full suite; the earlier successful persisted mobile demo booking was retained. This visual follow-up did not submit another booking or send real email.
- Local implementation only; no hosting deployment, remote push, real SMTP or customer UAT is implied.

## Implementation checklist

- [x] Resolve the exact selected option and preserve generated assets.
- [x] Update all three saved presets and owner controls.
- [x] Compare desktop source and implementation together, including readable focused regions.
- [x] Fix mobile photo width and sentence spacing; verify all profiles at 320/390 px.
- [x] Reconcile Figma palette variables and labels.
- [x] Complete functional and framework checks.

## Follow-up polish

The barber demo retains its previous custom business introduction; replace demo text with client-specific copy when onboarding a real salon. This is a content refinement, not a layout defect.

## Current color-depth refinement

User follow-up: the page still felt too white. The selected Atelier layout is retained; the color changes below explicitly supersede the earlier ivory palette.

- Source baseline: `output/design/editorial-hair-desktop-final.jpg` (1426 x 1014, CSS 1440 x 1024). Current capture: `output/design/warm-hair-desktop.jpg`, same dimensions and state.
- Full before/after comparison opened together: `output/design/warm-before-after.png`. Readable headline/CTA comparison: `output/design/warm-focus.png`.
- Additional evidence: `warm-hair-contact.jpg` (desktop), `warm-hair-mobile.jpg` and `warm-nails-mobile.jpg` (CSS 390 x 844), `warm-hair-mobile-320.jpg` (CSS 320 x 704), `warm-booking-mobile.jpg`, all in `output/design/`.
- [P2, fixed] Large near-white regions blended together. Hair now uses champagne #EFE3D4, sand #E2CDB7 and burgundy #6D2734. Nails uses richer powder #F1DEE4 / #E6CAD3. Dark barber colors remain unchanged. Header, service band, staff cards and filled service actions create a clear section rhythm; contact areas use the editable accent with automatically contrasting text and an inverse CTA.
- [P2, fixed during refinement] The first full-width service-band implementation inherited a large-screen wrap-padding override and misaligned its content. Kept the band full-width and moved the existing constrained wrap inside it. The post-fix desktop comparison confirms alignment with hero/header content.
- Typography, copy, generated photo crops, editable line breaks, routes and booking behavior remain intact. Only color, section framing and the service container changed. Real generated assets remain in use.
- Preset contrast checks initially caught a muted-text/background pair below 4.5. Darkened the muted tokens; the final Branding suite passes all 7 tests and 110 assertions. Pint and Blade cache also pass.
- Browser DOM: all three profiles have no horizontal overflow at 320 px. Hair and nails were additionally checked at 390 px. Hair photo width is 306.36 px at CSS 320. Native mobile menu opens and the hair booking form loads 16 available slots without overflow; captured console error list is empty. No new booking was submitted in this refinement.
- Figma foundation variables and visible color labels were updated to match the new palettes and visually reviewed.
- Intermediate captures affected by browser emulation scaling were rejected; the named final desktop and hair/nails mobile captures were opened and reviewed. Temporary emulation was cleared and the running preview retained.
- No actionable P0/P1/P2 findings remain. This continues to be a local demo, without a production deployment.

final result: passed
