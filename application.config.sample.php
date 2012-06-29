<?php

/*  Edit this configuration file to reflect the settings for your
    instance of LiveWhale and the people you wish to receive updates.
    
    Please use one of the following formats for email addresses below:
    
    account@school.tld
    First Last <account@school.edu> */

$config = array(

/*  The email addresses of the people you want to receive the notifications. */ 
  'recipients' => array(
    array('email'=>'Some Name <somename@example.com>','group_filters' => array('Group Name:'),//group_filters accept regex and substring matches for group names that will recieve the emails, otherwise all groups are selected
    ),

/*  The email address of the sender (LiveWhale).
    Use your webmaster/office email if preferred. */
  'from_email' => 'LiveWhale <livewhale@school.edu>',

/*  The email address for email sending errors, should they occur. */
  'error_email' => 'Webmaster <webmaster@school.edu>',

/*  The indent of the navigation items; the default is: "&nbsp; &nbsp; &nbsp;". */ 
  'indent' => "&nbsp; &nbsp; &nbsp;",

/*  The linebreak for the emails; the default is: "\n". */ 
  'linebreak' => "\n",

/*  The string to use when an item cannot be valued; the default is: "[blank]". */ 
  'blank' => "[blank]",

);

?>
