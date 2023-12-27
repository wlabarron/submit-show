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

Get started with the [installation instructions](https://github.com/wlabarron/submit-show/wiki/Installation).