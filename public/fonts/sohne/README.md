# Sohne Font

Sohne is a commercial font from **Klim Type Foundry**.

To use this font:

1. Purchase a license from https://klim.co.nz/retail-fonts/sohne/
2. Download the WOFF2 font files
3. Place them in this directory:
   - `Sohne-Buch.woff2` (Regular, 400 weight)
   - `Sohne-Halbfett.woff2` (SemiBold, 600 weight)
4. Uncomment the @font-face declarations in `assets/styles/fonts.css`
5. Rebuild Tailwind CSS: `php bin/console tailwind:build`

Until the font is installed, the design will fall back to Inter for display text.
