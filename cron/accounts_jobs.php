<?php
 /**
 * Front-end cron script for ZPanel xBilling Module
 * Version : 1.1.0
 * @author Aderemi Adewale (modpluz @ ZPanel Forums)
 * Email : goremmy@gmail.com
 * @desc Performs checks for Domain Expiration Reminder, Invoice Reminder, Domain Deletion 
*/
    $dir = '../';
    require_once('../config.php');
    require_once('../functions/xbilling.php');
    
    //Invoice Reminder
    InvoiceReminder();

    //Disable Expired Domains
    DisableExpiredDomains();
    
    //Renewal Reminder
    RenewalReminders();    
    
    //Delete Expired Domains after X Days - this will probably come in the next version
    DeleteExpiredDomains();
?>
