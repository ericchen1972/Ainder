# Ainder Client Image Preprocessing Implementation Plan

**Goal:** Prevent high-resolution registration photos from exhausting PHP memory by requiring both manual and Agent clients to upload a canonical 720 × 1280 WebP produced with a centered 9:16 cover crop.

**Architecture:** Browser JavaScript converts manual selections before populating the multipart form. WebMCP metadata instructs the Agent to perform the identical conversion before requesting a signed upload. PHP validates detected WebP MIME and exact dimensions before calling GD, then rewrites the bounded image to strip metadata and preserve the existing storage invariant.

## Task 1: Lock the public contracts with failing tests

- Extend the profile JavaScript contract test for 720 × 1280 canvas conversion, centered cover crop, WebP quality 0.84, generated WebP `File` objects, and processing-state submission gating.
- Extend photo validation tests so JPEG and incorrectly sized WebP inputs are rejected while exact 720 × 1280 WebP is accepted.
- Extend WebMCP tests so `prepare_photo_upload` accepts only `image/webp` and explicitly requires client preprocessing.
- Run the focused tests and confirm they fail for the intended missing behavior.

## Task 2: Implement strict server-side validation

- Add a validation helper that uses detected MIME and `getimagesize()` to require WebP at exactly 720 × 1280.
- Use it in manual staging before `ainder_process_image()`.
- Restrict Agent upload preparation to `image/webp` and validate the staged upload before GD decoding.
- Return structured photo validation failures without entering the high-memory original-image decode path.

## Task 3: Implement manual browser preprocessing

- Decode selected JPEG, PNG, and WebP files with orientation-aware browser APIs.
- Calculate the centered source rectangle using cover scaling for a 9:16 target.
- Draw into a 720 × 1280 canvas and encode WebP at quality 0.84.
- Replace selected originals with generated `.webp` files in `DataTransfer`.
- Preview the generated crop, show processing errors, and disable submit while conversion is active.

## Task 4: Update WebMCP Agent instructions

- Restrict `mime_type` schema to `image/webp`.
- Require a `.webp` filename and client-produced 720 × 1280 centered 9:16 crop in the tool description.
- Keep the main/supporting-photo content rules unchanged.

## Task 5: Verify, publish, and complete the live registration

- Run focused and full PHP test suites plus JavaScript syntax checks.
- Process the two supplied source photos locally without changing them.
- Commit and push `main`, verify deployment and live tool metadata.
- Start a fresh registration if the earlier session expired, upload the actual processed photos, submit the confirmed real profile data, and verify the authenticated app page.
