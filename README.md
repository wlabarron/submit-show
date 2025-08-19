# Submit Show tool
This tool allows a radio station to accept show recordings from presenters or producers, ready for playout or replay, 
and can automatically publish them to Mixcloud at a specified time.

* Allows people to send in radio shows without having to try and email large files.
* Automatically publishes shows to Mixcloud at the specified time, without sharing the Mixcloud password with the whole
  team.
* Can send an email notification to your scheduling team when a new show is submitted.
* Can send an email notification to the person who submitted the show once it's published on Mixcloud.
* Stores submitted show files in a local directory or in S3-compatible storage.
* Mixcloud publication notification emails can contain shortened URLs ready for sharing via [YOURLS](https://yourls.org) 
  integration.
* Can use SAML for authentication and getting addresses for email notifications. If SAML is enabled, it also takes a log
  of uploads and submissions via the user's name ID.
* Reads show names and presenters from a JSON file - so you can integrate with another existing system or write some 
  simple hand-crafted JSON.
* Make your Mixcloud uploads look consistent and professional with auto-written titles and a custom footer on all show 
  descriptions.
* Make submitting shows nice and easy by saving images, tags, and descriptions ready for next time.

## Installation
It's recommended to read through all of the instructions before you start to make sure your server is capable of handling all the bits which need to run. In future, this might become more automated and less error prone.

1. Get the latest release from GitHub: `git clone https://github.com/wlabarron/submit-show.git --branch main --depth=1`.
2. Make sure [ffmpeg](https://www.ffmpeg.org) and [MediaInfo](https://mediaarea.net/en/MediaInfo) is installed on your server. On Linux, you can install them with `apt install ffmpeg mediainfo`.
3. Install the rest of the dependencies: `composer install --no-dev`
3. Create a database and associated user. Remember to restrict the user to only the database you want to use for the Submit Shows tool. [Build the necessary tables](#building-tables).
4. Copy `processing/config.php.template` to `processing/config.php` and edit the configuration to your liking. You will need to make some changes here if you want the system to be functional.
5. If you're using `shows.json` as the URL of show data in `config.php`, copy `shows.json.example` to `shows.json` and edit it to your liking.
6. Set up a cron job to run the file `processing/cron.php` on a regular interval. This cron job is what moves show files to your uploads folder or offsite storage provider, deletes old shows, and publishes shows to Mixcloud. This may look like `* * * * * php -f /var/www/processing/cron.php`.
7. Configure your web server to serve the directory you've installed the project in. If you want to be thorough, you can forbid public access to `/processing/*`, `/vendor/*`, `/composer*`, `/.git/*`, `/.gitignore`, and `*.md`. The index page is `index.php`.

### Building tables
This is the command to run inside your new database to build the necessary schema.

``` sql
create table log
(
  id int auto_increment
    primary key,
  user text not null,
  action_type text not null,
  action_detail text not null,
  datetime datetime default current_timestamp() not null
);

create table saved_info
(
  `show` int not null
    primary key,
  description text null,
  image longblob null
);

create table saved_tags
(
  id int auto_increment
    primary key,
  tag text not null,
  `show` int not null
);

create index saved_tags_saved_info_show_fk
  on saved_tags (`show`);

create table submissions
(
  id int auto_increment
    primary key,
  file text not null,
  `file-location` text not null,
  title text not null,
  description text not null,
  `end-datetime` datetime not null,
  image longblob null,
  `deletion-datetime` datetime null,
  `notification-email` text null
);

create table tags
(
  id int auto_increment
    primary key,
  tag text not null,
  submission int not null
);

create index tags_submissions_id_fk
  on tags (submission);
```
