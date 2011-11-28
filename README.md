
## What It Is

This software is a LiveWhale application module that allows you to have LiveWhale email a select number of people when changes are made to navigations by site editors.

## Requirements

Although this software might work with earlier versions, it has only been tested with LiveWhale 1.4.2 or better.

## Installation

The easiest way to install this software is to use git to clone it into your livewhale/client/modules folder as follows:

    $ cd /path/to/your/livewhale/client/modules
    $ git clone git://github.com/lewisandclark/navigations_monitor.git

Git will then copy the most current version of the code into a navigations_monitor folder within client/modules.

If you don't have or are unable to use git, you can also download a zip or tarball from github (use the downloads button) and extract it manually into the livewhale/client/modules folder as `navigations_monitor`. (Don't change the name, it will make it non-functional in LiveWhale.)

## Configuration

Before you can use LiveWhale Navigations Monitor, you must fill out some basic information in its configuration file. If you've just installed it, you can duplicate the sample configuration as follows:

    $ cd ./navigations_monitor
    $ cp application.config.sample.php application.config.php

Then, open the `application.config.php` file in any code/text editor and set (at minimum) the email addresses for the recipients, the sender (your LiveWhale) and any errors emails (your webmaster perhaps).

## Usage

Once you have everything installed and configured it should simply begin working. (You might have to wait a few minutes if you have application caching in place.)

## Developers

If you have suggestions, contributions, errors, etc. please email me, or register an issue, or fork and issue a pull request. If you add additional functionality, your pull request must have corresponding supporting documentation.
