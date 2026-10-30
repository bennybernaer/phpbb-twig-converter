# Twig Converter for phpBB 4

This repository contains a phpBB 4 compatible build of **Twig Converter** by kasimi.

## Version

1.3.1

## Changes for phpBB 4

- Replaced the removed `Twig_Source` class with `Twig\Source`
- Added detection of phpBB 4 styles using `composer.json` with `type: phpbb-style`
- Requires PHP 8.2 or newer
- Targets phpBB 4.0.x

## Installation

Copy the extension to:

`ext/kasimi/twigconverter/`

Then enable **Twig Converter** in the phpBB ACP under **Customise → Manage extensions**.

The PHP ZIP extension must be enabled.

## Credits

Original extension by **kasimi**. The phpBB 4 compatibility changes are maintained in this repository by **bennybernaer**.

## License

GNU General Public License v2.0 only (GPL-2.0-only).
