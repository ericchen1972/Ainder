# Sweety Product Reference Images Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce six consistent, AI-readable fashion product image sets using realistic faceless exhibition mannequins, and rename the storefront categories to MEN and WOMEN.

**Architecture:** Generate one square master image for each outfit, then use that master as the locked visual reference for three separate 9:16 angle images. Save all 24 final assets to the mounted media volume, verify ratios and visual constraints, then update and re-read the SlimWeb category taxonomy through MCP.

**Tech Stack:** Built-in image generation tool, local image inspection, shell metadata checks, SlimWeb MCP.

---

## File Structure

- Create: `/Volumes/1TB/Codex-Media/images/sweety-products/men-suit/` — square master plus front, side, and back views.
- Create: `/Volumes/1TB/Codex-Media/images/sweety-products/men-leather-denim/` — square master plus front, side, and back views.
- Create: `/Volumes/1TB/Codex-Media/images/sweety-products/men-casual/` — square master plus front, side, and back views.
- Create: `/Volumes/1TB/Codex-Media/images/sweety-products/women-dress/` — square master plus front, side, and back views.
- Create: `/Volumes/1TB/Codex-Media/images/sweety-products/women-suit/` — square master plus front, side, and back views.
- Create: `/Volumes/1TB/Codex-Media/images/sweety-products/women-jacket-denim/` — square master plus front, side, and back views.
- Modify: SlimWeb categories `38` and `39` through MCP — rename to `MEN` and `WOMEN`.

### Task 1: Preflight storage and SlimWeb state

- [ ] Confirm `/Volumes/1TB` exists and `/Volumes/1TB/Codex-Media/images` is writable.
- [ ] Create the six destination directories without overwriting existing assets.
- [ ] Read SlimWeb categories and confirm IDs `38` and `39` still represent the existing male and female categories.

### Task 2: Rename storefront categories

- [ ] Update category `38` to `MEN` while preserving its parent, image, icon, description, and sort order.
- [ ] Update category `39` to `WOMEN` while preserving its parent, image, icon, description, and sort order.
- [ ] Re-read the category list and confirm the two navbar labels are `MEN` and `WOMEN`.

### Task 3: Generate MEN charcoal suit set

- [ ] Generate `men-suit-main.png`: square, full-body front three-quarter view, charcoal tailored two-piece suit, ivory shirt, dark restrained tie, black leather shoes, matte featureless male exhibition mannequin, seamless warm-gray studio.
- [ ] Generate `men-suit-front.png`: 9:16 straight front view using the square image as the locked reference.
- [ ] Generate `men-suit-side.png`: 9:16 right-side or rear three-quarter view using the square image as the locked reference.
- [ ] Generate `men-suit-back.png`: 9:16 full back view with a slight natural turn using the square image as the locked reference.

### Task 4: Generate MEN leather and denim set

- [ ] Generate `men-leather-denim-main.png`: square, black leather jacket, plain warm-gray inner layer, dark indigo straight jeans, understated dark shoes, matte featureless male exhibition mannequin.
- [ ] Generate `men-leather-denim-front.png`: 9:16 straight front view with jacket shape, zipper, seams, jeans, shoes, and mannequin locked to the master.
- [ ] Generate `men-leather-denim-side.png`: 9:16 side or rear three-quarter view with all clothing details locked.
- [ ] Generate `men-leather-denim-back.png`: 9:16 back view with all clothing details locked.

### Task 5: Generate MEN casual set

- [ ] Generate `men-casual-main.png`: square, oatmeal casual overshirt, ivory crew-neck knit, muted khaki relaxed trousers, minimal off-white sneakers, matte featureless male exhibition mannequin.
- [ ] Generate `men-casual-front.png`: 9:16 straight front view with all clothing details locked.
- [ ] Generate `men-casual-side.png`: 9:16 side or rear three-quarter view with all clothing details locked.
- [ ] Generate `men-casual-back.png`: 9:16 back view with all clothing details locked.

### Task 6: Generate WOMEN dusty-rose dress set

- [ ] Generate `women-dress-main.png`: square, dusty-rose long-sleeve midi dress with clean waist shaping and fluid skirt, understated cream shoes, matte featureless female exhibition mannequin.
- [ ] Generate `women-dress-front.png`: 9:16 straight front view with dress construction, hem, shoes, and mannequin locked to the master.
- [ ] Generate `women-dress-side.png`: 9:16 side or rear three-quarter view with all clothing details locked.
- [ ] Generate `women-dress-back.png`: 9:16 back view with all clothing details locked.

### Task 7: Generate WOMEN suit-inspired set

- [ ] Generate `women-suit-main.png`: square, taupe single-breasted blazer, light neutral inner layer, coordinated wide-leg trousers, understated shoes, matte featureless female exhibition mannequin.
- [ ] Generate `women-suit-front.png`: 9:16 straight front view with all clothing details locked.
- [ ] Generate `women-suit-side.png`: 9:16 side or rear three-quarter view with all clothing details locked.
- [ ] Generate `women-suit-back.png`: 9:16 back view with all clothing details locked.

### Task 8: Generate WOMEN short jacket and denim set

- [ ] Generate `women-jacket-denim-main.png`: square, cream short jacket, simple warm-white top, medium-blue straight jeans, understated cream shoes, matte featureless female exhibition mannequin.
- [ ] Generate `women-jacket-denim-front.png`: 9:16 straight front view with all clothing details locked.
- [ ] Generate `women-jacket-denim-side.png`: 9:16 side or rear three-quarter view with all clothing details locked.
- [ ] Generate `women-jacket-denim-back.png`: 9:16 back view with all clothing details locked.

### Task 9: Validate and hand off

- [ ] Inspect all 24 files for complete bodies, blank plastic faces, clean backgrounds, garment visibility, and absence of text, logos, watermarks, or props.
- [ ] Check image dimensions: six main images must be square and eighteen description images must be 9:16.
- [ ] Check each four-image set for matching garment color, seams, silhouette, layering, shoes, accessories, and mannequin proportions.
- [ ] List every saved absolute path and report any regenerated image separately.
