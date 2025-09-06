# Changelog
## Upgrade instructions
1. **Backup your installation, including show files and databases.**
2. Temporarily disable your cron job.
3. In your installation directory, run `git pull && composer update`.
4. Complete any additional steps specific to the version you're upgrading to *and all versions in between* (see below).
5. Re-enable your cron job.

## v2.1
In this release:
* Tag suggestions appear as you type in all five tag entry fields, rather than mandating a selection for the first tag and getting no help for the other four.
* This first tag can be anything, not just a Mixcloud default option.

## v2.0
In this release:
* Shows recorded on the server with a tool like [PlayIt Recorder](https://www.playitsoftware.com/Products/Recorder) can be topped-and-tailed and submitted, entirely in the web browser.
* The UI has been improved, simplifying its appearance, showing an upload progress meter, and making it more modular and easier to upgrade in future.
* Upload notification emails can be sent to multiple addresses (#130).
* Mixcloud's upload rate limits are handled properly (#7).
* The project has been restructured to make it easier to find things.
* Wording on the Mixcloud upload email has been improved to explain why the link doesn't work immediately.
* Saved Show images are loaded with a `height` attribute to prevent them making the page layout shift.
* Dependabot will check for updates less frequently (#86).

Special upgrade instructions:
1. Install [MediaInfo](https://mediaarea.net/en/MediaInfo), `apt install mediainfo`.
2. If using `shows.json` at the root of the project, move the file to `www/shows.json`.
3. Move `processing/config.php` to `config.php` (at the root of the project).
4. Check that the `processing` directory is empty, then delete it.
5. Inside `config.php`:
    1. At the start of the file, replace `require __DIR__ . "/../vendor/autoload.php";` with `require __DIR__ . "/vendor/autoload.php";`.
    2. Copy the new options under `ServerRecordings` from the `config.php.template` into your own config file.
6. Reconfigure your web server so the web root is `[project path]/www` instead of `[project path]`.
7. Update your crontab to run `/scripts/cron.php` instead of `/processing/cron.php`.

## v1.1
In this release:
* Show files can now be stored with whatever file name you wish, and even in a directory structure if you like (#26, #51).
* You can turn off the option to delete local files after the show has been published to Mixcloud (#36).
* Fixes to the frontend, including some accessibility fixes and layout tidying (#38, #41).
* Fix a bug which meant some parts of the frontend stopped working if this system wasn't installed on the root path (#30).

Special upgrade instructions:
1. Open `processing/config.php`. Replace:
   ```php
   // How long a show should be kept in storage after being published to Mixcloud -
   // format at https://www.php.net/manual/en/datetime.formats.relative.php
   'retentionPeriod' => '1 day',
   ```
   on lines 74-76 with the new options on lines 74-103 of `processing/config.php.template`. It might be easier to just copy `processing/config.php.template` to `processing/config.php` and re-enter your configuration details.
   
# v1
This is a substantial refactoring of the code to make the project clearer and easier to maintain going forward. There's improved documentation within the code, and now there's actual installation instructions so this project can be useful to more than just me! The frontend interface is now about half the size it was before and has a more semantic structure.