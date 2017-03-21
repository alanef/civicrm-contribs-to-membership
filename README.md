# civicrm-contribs-to-membership
## Script to update CiviCRM database.

This script is designed to be run by cron command line, e.g. daily or weekly  and performs two actions
relating to Importing of contributions by CSV

CiviCRM does not try to link CSV contributiosn to existing memberships or renew them

1. It renews memberships that have fallen into 'Grace' and have a subsequent contribution  uploaded via CSV Importing
2. It links contributions to memberships hence enableing contribution value reporting

This code specifically assumes annual membership renews to the end of teh current year contributions received in.
This works for me, but may not for you.


Much of this code came from 'Collins22' chicago-orienteering.org/civicrm_code.htm

This script is platform agnostic (WordPress/Drupal) and calls native PHP MySQL functions

Whilst the code could be converted to a CiviCRM extension it cuurently works for me, additionally it doesn't attempt
to use the API again for pragmatic reasons rather than athestics

*The code shouldn't be placed in a web accessible area as it contains database credentials*
