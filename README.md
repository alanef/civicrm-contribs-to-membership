# civicrm-contribs-to-membership
## Script to update CiviCRM database.

This script is designed to be run by cron command line, e.g. daily or weekly  and performs two actions
relating to Importing of contributions by CSV

CiviCRM does not try to link CSV contributions to existing memberships or renew them


1. It move Expired members to Grace if they have had a contribution the prior year - to handle year end late recording of contributions
2. It renews memberships that have fallen into 'Grace' and have a subsequent contribution  uploaded via CSV Importing
3. It links contributions to memberships hence enabling contribution value reporting

This code specifically assumes annual membership renews to the end of the current year contributions received in.
This works for me, but may not for you.


Much of this code came from 'Collins22' chicago-orienteering.org/civicrm_code.htm

This script is platform agnostic (WordPress/Drupal) and calls native PHP MySQL functions

Whilst the code could be converted to a CiviCRM extension it currently works for me, additionally it doesn't attempt
to use the API again for pragmatic reasons rather than atheistic


Replace the database credentials

 ```$hostname_CiviCRM = "localhost";
$database_CiviCRM = "yourdatabase";
$username_CiviCRM = "youruser";
$password_CiviCRM = "yourpassword";
```

*The code shouldn't be placed in a web accessible area as it contains database credentials*
