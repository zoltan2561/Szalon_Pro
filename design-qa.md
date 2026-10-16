# SzalonPRO 0.2 design QA

Date: 2026-10-16. Scope: configurable public appearance, three industry presets, generated assets, owner appearance editor and the existing booking journey.

## Visual truth and evidence

- Visual direction: `output/design/reference-premium.png` (Signature immersive, displayed third in the earlier concept set). The user subsequently requested generated images/logo and direct implementation of the premium appearance.
- Editable profile foundations: https://www.figma.com/design/KpvtZCXgsAV78AENAzP2qZ?node-id=3-137
- Actual generated asset board: https://www.figma.com/design/KpvtZCXgsAV78AENAzP2qZ?node-id=6-11
- Implementation: `http://127.0.0.1:8095/`, `http://127.0.0.1:8096/`, `http://127.0.0.1:8097/`.
- Desktop captures: `output/design/hair-desktop.jpg`, `barber-desktop.jpg`, `nails-desktop.jpg`, `presets-desktop.jpg`, `hair-split-desktop.jpg`.
- Mobile captures: `output/design/hair-mobile.jpg`, `nails-mobile.jpg`, `barber-mobile-320.jpg`, `barber-booking-320.jpg`, `presets-mobile-320.jpg`, `booking-success-mobile.jpg`.
- Full-view comparison, opened and reviewed together: `output/design/qa-desktop-final.png`.
- Focused typography/CTA/photo comparison, opened and reviewed together: `output/design/qa-hero-final.png`.
- Three mobile profiles reviewed together: `output/design/mobile-profiles.png`.

## Viewport, density and state

Desktop: measured CSS viewport 1440 × 1024. Chrome has devicePixelRatio 1.1; its viewport override was compensated and the DOM dimensions verified. The browser captures the content region at 1426 × 1014, omitting scrollbar/chrome. Source pixels: 1487 × 1058; normalized with aspect-preserving fit to the same 1426 × 1014 comparison region. No device frames were compared.

Mobile: measured CSS viewports 390 × 844 and 320 × 704. Saved browser content regions are 376 × 813 and 306 × 673 respectively. These are actual browser captures, not image-generated UI. Mobile figures preserve their source proportions. An intermediate desktop capture taken during window resizing was rejected and replaced after the measured viewport and a fresh render agreed.

State: public, published synthetic demo data; owner login only for settings. Stored business content, catalog and prices intentionally differ from the illustrative concept. The new SP mark and separately generated interior photographs replace the concept's illustrative assets, as requested. The three palettes and type families follow the editable Figma foundations. This is a configurable product implementation, not a pixel-identical copy of the concept's sample copy.

## Comparison history and fixes

1. [P1] The appearance partial initially appeared as literal Blade text because `@csrf` and `@include` were adjacent. Split the directives; verified the editor renders all layout, palette, upload and section controls. A regression assertion checks the rendered controls. Evidence: `presets-desktop.jpg`, `presets-mobile-320.jpg` and the browser DOM.
2. [P1] The real settings form submitted empty password fields, which triggered current-password validation. Added nullable handling while retaining the required current password for a requested password change. Verified a browser save, persisted split layout, restoration of the immersive preset, and a regression test that rejects password replacement without the current password.
3. [P2] The split hero photograph initially kept its intrinsic height and left an empty lower area. Made the actual image cover the full picture container. Post-fix evidence: `hair-split-desktop.jpg`.
4. [P2] Excess supporting hero copy pushed the services below the intended first-screen proportions. Removed the redundant note and limited the hero excerpt; the full introduction remains in its own section. Post-fix measured desktop hero: 650 px; first service is visible at the desktop target viewport.
5. [P2] Selected slot detail text inherited legacy colors. Bound it to the active preset's muted text color. Browser verified a selected barber slot at 320 px: light text on the dark surface, including the staff/offset detail. Evidence: `barber-booking-320.jpg`.
6. [P2] A flat photo dimming treatment was insufficiently predictable for bright custom images. Added a dedicated readable text-area overlay and a consistent mobile treatment while keeping actual photographs. Re-captured and reviewed both full-view and focused comparisons: `qa-desktop-final.png`, `qa-hero-final.png`, `mobile-profiles.png`.
7. Figma asset-board QA: WebP uploads did not render in the first export. Replaced the Figma image fills with original generated PNGs and repaired text wrapping; the website retains optimized WebP. Final visual proof: `output/design/figma-assets-final.png`, all four assets visible and no remaining shimmer.

## Required fidelity surfaces

- Fonts/typography: self-hosted DM Sans body and controls, Playfair Display editorial headings; sans display option for barber. Body is 16 px, forms remain 16 px on mobile, major controls are at least 48 px high. No clipped headings were found in the verified viewports.
- Spacing/layout rhythm: full-width photo masthead, clear primary action, open service rows, generous section spacing, two working hero layouts. Mobile sections stack; checked public home, booking and owner presets without horizontal overflow. The persistent mobile booking button stays inside the viewport.
- Colors/tokens: separate barber, hair and nails palettes; all default body/muted text combinations pass 4.5:1 on page and surface colors. Custom palette saves reject low contrast. Button foreground is chosen from the actual accent color. Focus and selected slot states remain visible.
- Image quality/assets: original generated SP mark with transparency; three separate industry photographs; actual desktop/mobile WebP files, no UI screenshot used as a page asset. Image geometry and crop checked in-browser. The logo is readable at header size. Licensed Phosphor assets supply UI icons; local font/icon licenses are included.
- Copy/content: real stored demo names, prices, duration and service links remain data-driven. No invented ratings, awards, client totals or testimonials. The demo and generated atmosphere images are identified. The full introduction and contact details remain editable.

## Interaction and backend evidence

- Mobile menu opened and exposed all four navigation links. Its booking action reached the existing booking form.
- A synthetic nails booking was completed from the 390 px browser view. Success screen verified; database readback confirms booking #2, confirmed, 2026-10-17 09:00 Europe/Budapest, 60 minutes, 8500 Ft. The mail is queued; no live SMTP delivery is claimed.
- Owner applied the hair preset, saved a custom split layout and edited demo copy, verified the persisted public result, then restored the immersive profile.
- Preset changes preserve business fields and uploaded media. Upload validation rejects SVG. The encrypted backup/restore test now covers the custom hero and appearance configuration.
- Browser console error checks on final public views: none.
- Final backend suite: 36 tests, 249 assertions passed. Pint, Blade cache, route cache, Composer strict validation and diff whitespace check passed.

## Follow-up polish and limits

- P3: an unusually long saved introduction is intentionally shortened in the hero; clients can tune the full editable copy for their own brand.
- Browser checks used Chrome. No physical iPhone/Safari verification, production hosting/MySQL pilot or real SMTP delivery was performed in this change.
- Figma remains editable foundations and an asset board; it is not a fabricated screenshot-only full-site design file.

## Implementation checklist

- [x] Three saved presets, owner customization and two layouts.
- [x] Generated logo/photos integrated, optimized and verified.
- [x] Mobile navigation and booking journey verified through persistence.
- [x] All actionable P0/P1/P2 findings fixed and rechecked.
- [x] Existing booking regression suite passes.

final result: passed
