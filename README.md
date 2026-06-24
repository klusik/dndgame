# DnD ChatGPT Campaign

DnD ChatGPT Campaign is a small PHP and JavaScript web application for managing long-running ChatGPT-assisted tabletop campaigns without using the OpenAI API.

The application stores canonical campaign metadata in MariaDB or MySQL and mirrors long text content to Markdown and JSON files under `storage`. It generates structured prompts for ChatGPT, accepts pasted ChatGPT responses, extracts narrative text and JSON state deltas, and lets the user review changes before applying them.

## Requirements

- PHP 8.0 or newer
- MariaDB or MySQL
- Apache with mod_rewrite
- PDO MySQL extension
- mbstring
- json
- openssl

## Installation

1. Upload the project files to the web root of your hosting directory.
2. Open the site in a browser.
3. If `config.php` does not exist, the installer starts automatically.
4. Enter database credentials and create the first admin account.
5. Delete installer access by keeping the generated `storage/install.lock` in place.

## Storage model

The database stores identities, indexes, relationships, file paths, status fields, and short structured values. Full prompts, full ChatGPT responses, narrative text, context exports, session exports, notes, and state mirrors are written to files.

## First workflow

1. Create a campaign.
2. Open the campaign.
3. Generate a prompt from the current state and the player action.
4. Copy the prompt to ChatGPT.
5. Paste the ChatGPT response back into the application.
6. Review the extracted JSON state delta.
7. Apply accepted changes.

## License

MIT.
