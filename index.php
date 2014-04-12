<?php
 /**
 * Front-end billing package for ZPanel xBilling Module
 * Version : 102
 * @author Aderemi Adewale (modpluz @ ZPanel Forums)
 * Email : goremmy@gmail.com
 * @desc Performs all basic operations from Basic User Registration to Creation of Hosting Domains
*/
    require_once('config.php');
    require_once('functions/xbilling.php');
    
    $settings = getSettings();

    $captcha_disabled = 1;
    $voucher_info = null;

    if((!isset($settings['recaptcha_disabled_yn']) || $settings['recaptcha_disabled_yn'] <> 1) && isset($settings['recaptcha_public_key']) && isset($settings['recaptcha_private_key'])){
       $captcha_disabled = 0;
       require_once('classes/recaptchalib.php');        
    }
    
    $error = 0;
    $error_msg = '';
    if(isset($_POST['section'])){
	$voucher_id = 0;
        $section = ((int) $_POST['section']) ? $_POST['section']:'1';
        $package_id = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
        $period_id = isset($_POST['period_id']) ? (int) $_POST['period_id'] : 0;

        $domain_name = isset($_POST['domain']) ? filter_var($_POST['domain'], FILTER_SANITIZE_STRING) : '';
        $domain_type_id = isset($_POST['domain_tid']) ? (int) ($_POST['domain_tid']) : 1;

        $fullname = isset($_POST['fullname']) ? filter_var($_POST['fullname'],FILTER_SANITIZE_STRING) : '';
        $email_address = isset($_POST['email_address']) ? filter_var($_POST['email_address'], FILTER_SANITIZE_STRING) : '';
        $address = isset($_POST['address']) ? filter_var($_POST['address'], FILTER_SANITIZE_STRING) : '';
        $postal_code = isset($_POST['postal_code']) ? filter_var($_POST['postal_code'], FILTER_SANITIZE_STRING) : '';
        $phone = isset($_POST['phone']) ? filter_var($_POST['phone'], FILTER_SANITIZE_STRING) : '';
        $username = isset($_POST['username']) ? filter_var($_POST['username'], FILTER_SANITIZE_STRING) : '';
        

        if(!$package_id){
            $error = 1;
            $error_msg = 'Please select a valid Package.';
        } elseif(!$period_id){
            $error = 1;
            $error_msg = 'Please select a valid Period for the selected package.';
        }
        
        if(!$error){
            switch($section){
                case 1:
                   $captcha_challenge =  $_POST['recaptcha_challenge_field'];
                   $captcha_response =  $_POST['recaptcha_response_field'];
                   
                   if(!$package_id){
                     $error = 1;
                     $error_msg = 'Please select a valid package.';
                   } elseif(!$period_id){
                     $error = 1;
                     $error_msg = 'Please select a valid service period.';
                   } elseif(!$domain_name){
                     $error = 1;
                     $error_msg = 'Domain name cannot be empty.';
                   } elseif(!$captcha_response && !$captcha_disabled){
                     $error = 1;
                     $error_msg = 'Please enter a reCaptcha response.';
                   }
                   
                   if(!$error && !$captcha_disabled){
                        $resp = recaptcha_check_answer ($settings['recaptcha_private_key'],
                                                        $_SERVER["REMOTE_ADDR"],
                                                        $_POST["recaptcha_challenge_field"],
                                                        $_POST["recaptcha_response_field"]);
                        if (!$resp->is_valid) {
                           $error = 1;
                           $error_msg = 'You supplied an invalid captcha response.';
                        }                 
                   }

                break;
                case 2:
                   /*$fullname = filter_var($_POST['fullname'], FILTER_SANITIZE_STRING);
                   $email_address = filter_var($_POST['email_address'], FILTER_SANITIZE_STRING);
                   $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
                   $postal_code = filter_var($_POST['postal_code'], FILTER_SANITIZE_STRING);
                   $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
                   $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
                   //$fullname = filter_var($_POST['firstname'], FILTER_SANITIZE_STRING);*/
                
                   if(!$fullname){
                     $error = 1;
                     $error_msg = 'Fullname cannot be empty.';
                   } elseif(!$email_address){
                     $error = 1;
                     $error_msg = 'Email Address cannot be empty.';
                   } elseif(!$username){
                     $error = 1;
                     $error_msg = 'Username cannot be empty.';
                   }
                   
                   if(!$error){
                    if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
                         $error = 1;
                         $error_msg = 'Email Address does not appear to be in a valid format.';
                    }
                   }
                   
                   if(!$error){
                     if(checkUserName($username) <> 0){
                         $error = 1;
                         $error_msg = 'Specified username is not available.';                        
                     }
                   }
                break;
                case 3:
                   //is there a voucher code entered...verify code
                   if(filter_var($_POST['code'], FILTER_SANITIZE_STRING)){
                   	$voucher_info = voucherInfo(filter_var($_POST['code'], FILTER_SANITIZE_STRING));
                   	$voucher_id = $voucher_info['id'];
                   	
			//die(var_dump($voucher_info));
                     if(!is_array($voucher_info)){
                         $error = 1;
                         $error_msg = 'Invalid voucher code supplied.';                        
                     }
			
                   }
                break;
            }        
        }

        if(!$error && !$voucher_info){
          $section++;
        }
    }
    
    if(!isset($section)){
        $section = 1;
    }    
    
    if($section == 1){
        $packages = getPackages();
        if(is_array($packages) && !isset($packages[0])){
            $pkgs[0] = $packages;
            $packages = $pkgs;
            unset($pkgs);
        }
    } elseif($section == 3){
        $package_name = getPackageName($package_id);
        $period_info = getPeriodInfo($package_id, $period_id);
        if(is_array($period_info)){
            $period_duration = $period_info['duration'];
            $period_amount = number_format($period_info['amount'],2);
            $period_duration .= ' Month';
            if($period_info['duration'] > 1){
                $period_duration .= 's';
            }
            
            if(isset($voucher_info['discount'])){
            	$period_amount_discount = ((float) $period_amount / 100) * (float) $voucher_info['discount'];
            } else {
            	$period_amount_discount = 0;
            }
        }
    } elseif($section == 4){
        $new_user = registerUser();

        $invoice_url = '';
        if(!$new_user['error'] && $new_user['result']){
            if($settings['website_billing_url']){
                $invoice_url = $settings['website_billing_url'].'/view_invoice.php?invoice='.$new_user['result'];
            }
            unset($_POST);
        } else {
            $error = 1;
            //$error_msg = "An internal error has occured, cannot create account at this time.";
            $result_msg = $new_user['result'];
        }
        
    }
    
  if(!isset($domain_name)){
    $domain_name = '';
  }
  
  $js_header = js_header();  
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo($settings['company_name']);?></title>
<script src="res/jquery-1.4.2.js" type="text/javascript"></script>
<link href="css/style.css" rel="stylesheet" type="text/css" />
<style type="text/css">
	#header {
		background: url(<?php echo($settings['company_logo_path']);?>) no-repeat;	
		background-position: center;	
	}
</style>
<script language="javascript" type="text/javascript">
<!--//--><![CDATA[//>
<!--
	<?php echo($js_header);?>  
//--><!]]>
</script>
</head>

<body>
	<div id="container">
    	<div id="header">&nbsp;</div>
            <?php
                if($error && $error_msg){
            ?>
                    <div class="error">
                        <?php echo($error_msg);?>
                    </div>
                    <div class="clear">&nbsp;</div>
            <?php 
                }
            ?>
        <div id="content">
            <form name="frm_order" id="frm_order" action="" method="post">
                <input type="hidden" id="package_id" name="package_id" value="<?php echo($package_id);?>" />
                <input type="hidden" id="period_id" name="period_id" value="<?php echo($period_id);?>" />
                <input type="hidden" id="section" name="section" value="<?php echo($section);?>" />
                <?php 
                    if($section > 1){
                ?>
                <input type="hidden" id="domain" name="domain" value="<?php echo($domain_name);?>" />
                <input type="hidden" id="domain_tid" name="domain_tid" value="<?php echo($domain_type_id);?>" />
                <?php
                    }
                ?>
                <?php 
                    if($section > 2){
                ?>
                <input type="hidden" name="fullname" value="<?php echo($fullname);?>" />
                <input type="hidden" name="email_address" value="<?php echo($email_address);?>" />
                <input type="hidden" name="address" value="<?php echo($address);?>" />
                <input type="hidden" name="postal_code" value="<?php echo($postal_code);?>" />
                <input type="hidden" name="phone" value="<?php echo($phone);?>" />
                <input type="hidden" name="username" value="<?php echo($username);?>" />
                <input type="hidden" name="voucher_id" value="<?php echo($voucher_id);?>" />
                <?php
                    }
                ?>
            <?php 
             switch($section){
                //hosting info
                case 1:
                  if(is_array($packages)){
            ?>
                <h3>Please select a hosting package below:</h3>
                <!-- <div id="packages"> -->
            <?php
                     foreach($packages as $pkg_idx=>$package){
                       $srvc_periods = $package['service_periods'];
                        
                        
            ?>
                      <div id="pkg_<?php echo($package['id']);?>" class="package">
                        <!-- <input type="radio" name="rd_package" id="rd_pkg_<?php echo($pkg_idx);?>"> -->
                         <span class="package"><?php echo($package['name']);?>
                        <div class="package_desc">
                           <?php echo(nl2br($package['desc']));?> 
                        </div>
                        <div class="package_selected" id="selected_pkg_<?php echo($package['id']);?>" style="display: none;">
                            selected
                        </div>
                        <div id="select_pkg_<?php echo($package['id']);?>" class="package_select" onclick="_select_pkg('<?php echo($package['id']);?>');">
                            <img src="images/icon_ok.png" align="top" alt="select package" hspace="2" />select
                        </div> 
                        <input type="hidden" id="pkg_periods_<?php echo($package['id']);?>" value="<?php echo($package['service_periods']);?>" />
                      </div>
                 <!-- </div> -->
            <?php       
                    }
            ?>
                    <div class="clear">&nbsp;</div>
                    <div class="clear">&nbsp;</div>
                    <div id="package_periods" class="left">
                        <h3>Select a service period below:</h3>
                        <div id="service_period">
                            Please select a hosting package above.
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div class="clear">&nbsp;</div>

                    <div id="domain_info" class="" style="margin-right: 20px;">
                        <h3>Domain:</h3>
                        www.<input type="text" name="domain" id="domain" size="15" value="<?php echo($domain_name);?>" />
                        <input type="hidden" name="domain_tid" id="domain_type_own" value="1" />
                        <!-- <br />
                        <div align="left" style="margin-left: 25px;">
                            <input type="radio" name="domain_tid" id="domain_type_own" value="1"<?php if(!$domain_type_id || $domain_type_id == 1){echo(' checked="checked"');}?> /> I own this domain.<br />
                            <input type="radio" name="domain_tid" id="domain_type_new" value="2"<?php if($domain_type_id == 2){echo(' checked="checked"');}?> /> I want to buy this domain.
                       </div> -->
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div class="clear">&nbsp;</div>
                    <?php if(!$captcha_disabled){?>
                    <script type="text/javascript">
                     var RecaptchaOptions = {
                        theme : 'clean'
                     };
                     </script>                    
                    <div id="recaptcha" class="clear" align="center" style="">
                        <h3>Please fill in the challenge below:</h3>
                        <?php echo recaptcha_get_html($settings['recaptcha_public_key']);?>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <?php } ?>
                    <div id="btn" class="right">
                        <input type="submit" name="btn_submit" value="Next &raquo;" class="submit">
                    </div>
            <?php
                  } else {
                    echo('<p>&nbsp;</p><div align="center" style="color: #f00;font-size:15px;">There are no hosting packages available at this time.</div>');
                  }
                break;
                //account info
                case 2:
            ?>
                <h3>Please enter new user account account information below:</h3>
                <div class="clear">&nbsp;</div>
                <div id="account_info">
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Fullname:</span></div> 
                        <div align="left" class="left">
                            <input type="text" name="fullname" id="fullname" size="15" value="<?php echo($fullname);?>" />
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                            <span class="label">Email Address:</span>
                        </div> 
                        <div align="left" class="left">
                            <input type="text" name="email_address" id="email_address" size="15" value="<?php echo($email_address);?>" />
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                            <span class="label">Address:</span>
                        </div>
                        <div align="left" class="left">
                            <textarea cols="25" rows="3" name="address"><?php echo($address);?></textarea>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                            <span class="label">Postal Code:</span>
                        </div>
                        <div align="left" class="left">
                            <input type="text" name="postal_code" id="postal_code" size="15" value="<?php echo($postal_code);?>" />
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                            <span class="label">Phone #:</span> 
                        </div>
                        <div align="left" class="left">
                            <input type="text" name="phone" id="phone" size="15" value="<?php echo($phone);?>" />
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <h3>Control Panel Information:</h3>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                            <span class="label">Username</span> 
                        </div>
                        <div align="left" class="left">
                            <input type="text" name="username" id="username" size="10" value="<?php echo($username);?>" />
                        </div>
                    </div>
                </div> 
                <div class="clear">&nbsp;</div>           
                <div id="btn" class="right">
                    <!-- <input type="submit" name="btn_submit" value="&laquo; Back" class="submit btn" onclick="$('#section').val(0);">&nbsp; --><input type="submit" name="btn_submit" value="Next &raquo;" class="submit">
                 </div>
            <?php
                break;
                //review order
                 case 3:
            ?>
                <h3>Please review your order information below:</h3>
                <div class="clear">&nbsp;</div>
                <div id="review_order">
                    <h3>Hosting Information</h3>
                    <hr size="1" width="95%" align="left" />
                    <div align="left" style="padding-left: 5px;">
                        <div align="left" class="left label"><span class="label">Hosting Package:</span></div> <br />
                        <div align="left" style="padding-left: 10px;">
                            <?php echo($package_name);?> for <?php echo($period_duration);?> @ <?php echo($period_amount);?> <?php echo($settings['currency']);?>
                            <?php if(isset($voucher_info['discount'])){?>
                            	<!-- <br />Discount @ <?php echo $voucher_info['discount'];?>% -->
                            <?php } ?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left" style="padding-left: 5px;">
                        <div align="left" class="left label"><span class="label">Domain:</span></div>
                        <br />
                        <div align="left" style="padding-left: 5px;">
                            <?php 
                                $domain_type = ($domain_type_id == 1) ? 'I own this domain':'I want to buy this domain';
                            ?>
                            <?php echo($domain_name);?>(<span class="ok"><?php echo($domain_type);?></span>)
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <h3>Account Information</h3>
                    <hr size="1" width="95%" align="left" />
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Fullname:</span></div> 
                        <div align="left" class="left">
                            <?php echo($fullname);?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Email Address:</span></div> 
                        <div align="left" class="left">
                            <?php echo($email_address);?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Address:</span></div> 
                        <br />
                        <div align="left" class="left" style="padding-left: 5px;">
                            <?php 
                                if(!$address){
                                    echo('Not Provided');
                                } else {
                                  echo($address);
                                }
                            ?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Postal Code:</span></div> 
                        <div align="left" class="left">
                            <?php 
                                if(!$postal_code){
                                    echo('Not Provided');
                                } else {
                                  echo($postal_code);
                                }
                            ?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Phone:</span></div> 
                        <div align="left" class="left">
                            <?php 
                                if(!$phone){
                                    echo('Not Provided');
                                } else {
                                  echo($phone);
                                }
                            ?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <h3>Control Panel Information</h3>
                    <hr size="1" width="95%" align="left" />
                    <div align="left">
                        <div align="left" class="left label"><span class="label">Username:</span></div> 
                        <div align="left" class="left">
                            <?php echo($username);?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <h3>Discount Voucher</h3>
                    <hr size="1" width="95%" align="left" />
                    <div align="left">
                        <div align="left" class="left label">
                        <span class="label"><?php if(!isset($voucher_info['discount'])){ echo 'Enter Voucher Code'; } else { echo  'Voucher';} ?>:</span>
                        </div> 
                        <div align="left" class="left">
                            <?php if(!isset($voucher_info['discount'])){?>
                            <input type="text" name="code" id="voucher_code" size="10" />
                            <input type="submit" name="btn_voucher" value="Apply" class="submit" style="width: auto;">
                            <?php } else { ?>
                            	<?php echo $voucher_info['code'];?>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="clear">&nbsp;</div>
                    <h3>Payment Total</h3>
                    <hr size="1" width="95%" align="left" />
                    <div align="left">
                        <div align="left" class="left label">
                        <span class="label">Sub Total:</span>
                        </div> 
                        <div align="left" class="left">
                            <?php echo($period_amount.' '.$settings['currency']);?>
                        </div>
                    </div>
                    <?php if(isset($voucher_info['discount'])){?>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                        <span class="label">Discount:</span>
                        </div> 
                        <div align="left" class="left">
                            <?php echo($voucher_info['discount']);?>% <?php echo($voucher_info['type']);?> - <?php echo($period_amount_discount.' '.$settings['currency']);?>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="clear">&nbsp;</div>
                    <div align="left">
                        <div align="left" class="left label">
                        <span class="label">Total:</span>
                        </div> 
                        <div align="left" class="left"><?php echo (float) ($period_amount - $period_amount_discount); ?> <?php echo $settings['currency'];?>
                        </div>
                    </div>
                    <div class="clear"><p>&nbsp;</p></div>
                    <div id="btn" class="right">
                        <input type="submit" name="btn_submit" value="&laquo; Back" class="submit btn" onclick="$('#section').val(1);">&nbsp;<input type="submit" name="btn_submit" value="Complete Order &raquo;" class="submit">
                     </div>
                    
                </div>            
            <?php        
                break;
                //Thank you
                 case 4:
            ?>
                <?php if(!$error){?>
                <h3 style="font-size: 20px;">Thank you for your Order!</h3>
                <?php } else {?>
                <h3 class="error" style="font-size: 20px; font-weight: bold;">Error!</h3>                
                <?php } ?>
                <div class="clear">&nbsp;</div>
                <div id="payment_thanks">
                    <?php if(!$error){?>
                    Thank you for your interest in <?php echo($settings['company_name']);?>.
                    <br /><br />
                    An email has been sent to <?php echo($email_address);?>.
                    <br /><br />
                    Please click <a href="<?php echo($invoice_url);?>">here</a> to view your invoice.
                    <?php } else {?>
                        <div align="center">
                            <span class="error" style="font-size: 13px;"><?php echo($result_msg);?></span>
                        </div>
                        <br/>
                        <div align="center">
                            click <a href="index.php">here</a> to start again.
                        </div>
                    <?php } ?>
                </div>
            <?php
             }
            ?>
            </form>
        </div>
        <div class="clearfix"><p>&nbsp;</p></div>
    	<div id="footer">
        	<?php if(isset($settings['email_address'])){?>
        	    <strong>Support:</strong> <?php echo($settings['email_address']);?><br />
        	<?php } ?>
        	Copyright &copy; <?php echo($settings['company_name']);?> <?php echo(date("Y"));?>, All rights reserved.
        </div>
    </div>
</body>
</html>
