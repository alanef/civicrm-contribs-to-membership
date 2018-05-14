<?php
/*    Script to update CiviCRM database.

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

The code shouldn't be placed in a web accessible area as it contains database credentials


 */

$log = new Logging();
$log->lwrite("starting");

$hostname_CiviCRM = "localhost";
$database_CiviCRM = "yourdatabase";
$username_CiviCRM = "youruser";
$password_CiviCRM = "yourpassword";


$CiviCRM = mysqli_connect("p:".$hostname_CiviCRM, $username_CiviCRM, $password_CiviCRM, $database_CiviCRM) or trigger_error(mysqli_connect_error());


// Really not sure what this is about but it works ( or seems to)
if (!function_exists("GetSQLValueString")) {
    function GetSQLValueString($conn, $theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "")
    {
        if (PHP_VERSION < 6) {
            $theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;
        }

        $theValue = function_exists("mysqli_real_escape_string") ? mysqli_real_escape_string($conn, $theValue) : mysqli_escape_string($conn, $theValue);

        switch ($theType) {
            case "text":
                $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
                break;
            case "long":
            case "int":
                $theValue = ($theValue != "") ? intval($theValue) : "NULL";
                break;
            case "double":
                $theValue = ($theValue != "") ? doubleval($theValue) : "NULL";
                break;
            case "date":
                $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
                break;
            case "defined":
                $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
                break;
        }
        return $theValue;
    }
}


// this query handles renewing grace memberships
$query_renewGraceMemberships = "UPDATE civicrm_membership AS m
inner join civicrm_membership_status ms on ms.id = m.status_id AND ms.name = 'Grace'
inner join civicrm_contact c on m.contact_id = c.id
inner join civicrm_contribution cn on c.id = cn.contact_id AND YEAR(cn.receive_date) = YEAR(CURDATE())
SET m.end_date = DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAYOFYEAR(CURDATE()) DAY), INTERVAL 1 YEAR),
m.status_id = 2";

$rsrenewGraceMemberships = mysqli_query($CiviCRM, $query_renewGraceMemberships) or die(mysqli_error($CiviCRM));
$log->lwrite("Grace Membership ".print_r($rsrenewGraceMemberships,true));


// this is from the original code
$query_rsMembershipPayments = "SELECT civicrm_contribution.id AS ContributionID, civicrm_contribution.contact_id AS ContributionContactID,
civicrm_contribution.financial_type_id, civicrm_contribution.receive_date, civicrm_contribution.total_amount, civicrm_contribution.trxn_id,
civicrm_contribution.source AS ContributionSource, civicrm_contribution.contribution_status_id, civicrm_membership.id AS MembershipID,
civicrm_membership.contact_id AS MembershipContactID, civicrm_membership.membership_type_id, civicrm_membership.join_date, civicrm_membership.start_date, civicrm_membership_payment.id AS MembershipPaymentID, civicrm_membership_payment.membership_id, civicrm_membership_payment.contribution_id FROM civicrm_membership right join (civicrm_membership_payment right join civicrm_contribution on civicrm_membership_payment.contribution_id = civicrm_contribution.id) on civicrm_membership.id = civicrm_membership_payment.membership_id";
$rsMembershipPayments = mysqli_query($CiviCRM, $query_rsMembershipPayments) or die(mysqli_error($CiviCRM));
$row_rsMembershipPayments       = mysqli_fetch_assoc($rsMembershipPayments);
$totalRows_rsMembershipPayments = mysqli_num_rows($rsMembershipPayments);

do {
    // look to see if MembershipContactID is null

    if ($row_rsMembershipPayments['MembershipID']) {

    //    $log->lwrite("Nothing to do here for mem ID  " . $row_rsMembershipPayments['MembershipID']);

    } else {

        // if it is null, look to find a membership record with the correct contact ID and a Join Date or Start Date that matches the Contribution Receive Date

        $contactID_rsMembershipCandidate = "-1";
        if (isset($row_rsMembershipPayments['ContributionContactID'])) {
            $contactID_rsMembershipCandidate = $row_rsMembershipPayments['ContributionContactID'];
        }
        $startdate_rsMembershipCandidate = "-1";
        if (isset($row_rsMembershipPayments['receive_date'])) {
            $startdate_rsMembershipCandidate = substr($row_rsMembershipPayments['receive_date'], 0, 10);
        }
        $joindate_rsMembershipCandidate = "-1";
        if (isset($row_rsMembershipPayments['receive_date'])) {
            $joindate_rsMembershipCandidate = substr($row_rsMembershipPayments['receive_date'], 0, 10);
        }

        $query_rsMembershipCandidate = sprintf("SELECT m.id, contact_id, membership_type_id, join_date, start_date
   FROM civicrm_membership m, civicrm_membership_status ms WHERE contact_id= %s
   AND  m.status_id = ms.id
   AND ms.is_current_member = 1
AND (YEAR(start_date) = YEAR(%s) OR YEAR(join_date)=YEAR(%s))", GetSQLValueString($CiviCRM, $contactID_rsMembershipCandidate, "int"), GetSQLValueString($CiviCRM,$startdate_rsMembershipCandidate, "date"), GetSQLValueString($CiviCRM,$joindate_rsMembershipCandidate, "date"));
        $rsMembershipCandidate = mysqli_query($CiviCRM, $query_rsMembershipCandidate) or die(mysqli_error($CiviCRM));
        $row_rsMembershipCandidate       = mysqli_fetch_assoc($rsMembershipCandidate);
        $totalRows_rsMembershipCandidate = mysqli_num_rows($rsMembershipCandidate);

        if ($totalRows_rsMembershipCandidate > 1) {

            // if it finds more than one record, put up a note, and don't create anything.
            $log->lwrite("More than one record nothing to do for contact ID  " . $contactID_rsMembershipCandidate );

        } elseif (!$row_rsMembershipCandidate['id']) {
    //        $log->lwrite("Nothing to do here , no member found ");

        } else {

            // if it finds a single record, create a link in civicrm_membership_payments that ties together the membership_id and contribution_id

            $insertSQL = sprintf("INSERT INTO civicrm_membership_payment (membership_id, contribution_id) VALUES (%s, %s)", GetSQLValueString($CiviCRM,$row_rsMembershipCandidate['id'], "int"), GetSQLValueString($CiviCRM,$row_rsMembershipPayments['ContributionID'], "int"));

            $Result1 = mysqli_query($CiviCRM, $insertSQL) or die(mysqli_error($CiviCRM));

            $ContributionID_rsNewMembershipPayment = "-1";
            if (isset($row_rsMembershipPayments['ContributionID'])) {
                $ContributionID_rsNewMembershipPayment = $row_rsMembershipPayments['ContributionID'];
            }
            $MemberID_rsNewMembershipPayment = "-1";
            if (isset($row_rsMembershipCandidate['id'])) {
                $MemberID_rsNewMembershipPayment = $row_rsMembershipCandidate['id'];
            }
            $query_rsNewMembershipPayment = sprintf("SELECT id, membership_id, contribution_id FROM civicrm_membership_payment WHERE contribution_id= %s AND membership_id = %s ", GetSQLValueString($CiviCRM,$ContributionID_rsNewMembershipPayment, "int"), GetSQLValueString($CiviCRM,$MemberID_rsNewMembershipPayment, "int"));
            $rsNewMembershipPayment = mysqli_query($CiviCRM, $query_rsNewMembershipPayment) or die(mysqli_error($CiviCRM));
            $row_rsNewMembershipPayment       = mysqli_fetch_assoc($rsNewMembershipPayment);
            $totalRows_rsNewMembershipPayment = mysqli_num_rows($rsNewMembershipPayment);

            if ($row_rsNewMembershipPayment['id']) {
                $log->lwrite("Created link for contact ID  " . $contactID_rsMembershipCandidate );
            } else {
                $log->lwrite("Failed to create link for contact ID  " . $contactID_rsMembershipCandidate);
            }
            mysqli_free_result($rsNewMembershipPayment);
        }
        mysqli_free_result($rsMembershipCandidate);
    }

} while ($row_rsMembershipPayments = mysqli_fetch_assoc($rsMembershipPayments));

mysqli_free_result($rsMembershipPayments);

class Logging
{

    // write message to the output
    public function lwrite($message)
    {
        // define script name
        $script_name = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
        // define current time and suppress E_WARNING if using the system TZ settings
        // (don't forget to set the INI setting date.timezone)
        $time        = @date('[d/M/Y:H:i:s]');
        // write current time, script name and message to the log file
        printf("$time ($script_name) $message" . PHP_EOL);
    }
}
?>
