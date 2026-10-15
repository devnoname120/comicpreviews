# Comic Previews

A Nextcloud preview provider for comic book archives in **CBZ** and **CBR** format.

The app registers a preview provider with Nextcloud so comic archives can display thumbnails in the Files interface and other places that use Nextcloud previews.

## Status

This project is in early development. It has been tested with a personal library of 287 comic archives (285 CBR and 2 CBZ). Your results may vary with other archives.

## Requirements

- Nextcloud 34 or newer
- PHP ZIP extension (for CBZ files)
- The `unrar` command-line utility (for CBR files)
- A Nextcloud-supported image backend, such as GD or Imagick

CBR support requires `unrar` to be installed and executable by the PHP/Nextcloud process.

## Installation

The app is not yet packaged for the Nextcloud App Store. For now, install it manually:

1. Copy the app directory into your Nextcloud `apps/` directory as `comicpreviews`.
2. Ensure the web/PHP user can read the app files and execute `unrar`.
3. Enable the app:

   ```bash
   sudo -u www-data php /var/www/nextcloud/occ app:enable comicpreviews
   ```

4. Generate a preview for a test comic or let your existing preview-generation workflow process it.

Adjust the Nextcloud path and PHP user for your installation.

## Supported formats

- **CBZ** — reads images from the ZIP archive.
- **CBR** — lists and extracts images using `unrar`.

The provider selects an image from the archive to use as the preview. Password-protected archives and unusual archive layouts may not work.

## Preview generation

This app provides the preview provider; it does not schedule preview generation itself. You can use Nextcloud's preview commands or a compatible preview-generation app to create previews in advance.

Example:

```bash
sudo -u www-data php /var/www/nextcloud/occ preview:generate "/username/files/path/to/comic.cbz"
```

Replace the username and file path with a path valid for your Nextcloud installation.

## Security notes

Only install this app from a source you trust. CBR preview generation invokes the external `unrar` utility, and archives may be untrusted input. Keep Nextcloud, PHP, and system packages up to date.

## Development note

This project was vibe-coded with the assistance of AI tools. The code has been tested against a personal library of 287 comic archives (285 CBR and 2 CBZ), but broader testing and community review are welcome.

## Experimental status and security

This project is experimental and has **not undergone an independent security audit**. Testing on one personal library does not guarantee compatibility, reliability, or security with all archives and environments.

CBR preview generation invokes the external `unrar` utility and processes archive contents. Use caution with untrusted files, keep dependencies up to date, and review the code before deploying it in environments where security is critical.

## Contributing

Issues and pull requests are welcome. Please include your Nextcloud version, PHP version, archive format (CBZ or CBR), and relevant logs when reporting a problem. Do not attach copyrighted comic archives.

## License

This project is licensed under the GNU General Public License v3.0 or (at your option) any later version. See [LICENSE](LICENSE) for the full text.
