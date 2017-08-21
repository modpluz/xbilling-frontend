<?php
 /**
 * Front-end billing package for ZPanel/Sentora xBilling Module
 * Version : 1.2.0
 * @author Aderemi Adewale (modpluz @ Sentora Forums)
 * Email : goremmy@gmail.com
 * @desc Payment Invoice
*/
    $invoice_reference = filter_var($_GET['invoice'], FILTER_SANITIZE_STRING);
    $error = 0;
    
    if(!$invoice_reference){
        die('<div align="center" style="color: #f00;font-size:15px;">Invoice not found!</div>');
    }
    require_once('config.php');
    require_once('functions/xbilling.php');
    
    $settings = getSettings();

    $invoice_info = fetchInvoice($invoice_reference);    
    
    if($invoice_info['error']){
        die('<div align="center" style="color: #f00;font-size:15px;">'.$invoice_info['message'].'</div>');
    }
    
    $invoice_info = $invoice_info['result'];

    $invoice_info['discount_amount'] = 0;
    if(isset($invoice_info['discount'])){
    	$invoice_info['discount_amount'] = (float) ($invoice_info['total_amount'] / 100) * $invoice_info['discount'];
    }
    
    $customer_info = fetchUserInfo($invoice_info['user_id']);
    if($customer_info['error']){
        die('<div align="center" style="color: #f00;font-size:15px;">'.$customer_info['message'].'</div>');
    }
    
    $customer_info = $customer_info['result'];
    
    //payment methods
    $payment_methods = fetchPaymentMethods();
    if($payment_methods['error']){
        die('<div align="center" style="color: #f00;font-size:15px;">'.$payment_methods['message'].'</div>');
    }
    $html_option = '';
    if(is_array($payment_methods['result'])){
        foreach($payment_methods['result'] as $js_method){
            $method = json_decode($js_method,true);
            if(is_array($method)){
                if($method['html']){
                    if(strpos($invoice_info['total_amount'], ".") === false){
                        $invoice_info['total_amount'] .= '.00';
                    }
                    $method_html = PaymentOptionHTML($method['id'],urldecode($method['html']),$invoice_info);
					$method_html = str_replace("{{country_code}}", $settings['country_code'], $method_html);
                    $method_html = str_replace("{{invoice_desc}}", $invoice_info['desc'], $method_html);
                    $method_html = str_replace("{{invoice_id}}", $invoice_info['reference'], $method_html);
                    $method_html = str_replace("{{invoice_amount}}", $invoice_info['total_amount'], $method_html);
                    $method_html = str_replace("{{currency}}", $settings['currency'], $method_html);
                    $method_html = str_replace("{{discount_rate}}", $invoice_info['discount_amount'], $method_html);
                }
                
                $payment_methods_html[$method['id']] = $method_html;
                
                //echo($method_html.'<br><br>');
                
                
                $html_option .= '<option value="'.$method['id'].'"';
                if($method['selected_yn'] == 1){
                  $html_option .= ' selected="selected"';
                }
                $html_option .= '>'.$method['name'].'</option>';
            }
        }
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo($settings['company_name']);?> - View Invoice</title>
<script src="res/jquery-1.4.2.js" type="text/javascript"></script>
<link href="css/style.css" rel="stylesheet" type="text/css" />
<style type="text/css">
	#header {
		/*width: 400px;*/
		height: 100px;
		max-height: 100px;
		background: url(<?php echo($settings['company_logo_path']);?>) no-repeat;
		background-position: center;	
	}
</style>
<script language="javascript" type="text/javascript">
<!--//--><![CDATA[//>
$(function(){
    _payment_method($('#payment_option_id').val());
});

function _payment_method(method_id){
   //hide payment methods
   $.each($('#payment_method_html').children(), function(idx,_div){
       $(_div).hide();
   });
    
    //show selected method html
    if(method_id){
        $('#payment_method_id').val(method_id);
        $('#payment_method_html_'+method_id).show();
    }
}
<!--
//--><!]]>
</script>
</head>

<body>
<div class="signup signup-body">
	<div id="container">
    <div class="flex-container">
    	<div id="header">&nbsp;</div>
            <?php
                if($error && $error_msg){
            ?>
                    <div class="error">
                        <?php echo($error_msg);?>
                    </div>
                    <div class="clear">&nbsp</div>
            <?php 
                }
            ?>
        <div id="content" style="height: 540px;">
            <?php if(!$error){?>
                <div align="center">            
                    <h3 style="font-size: 18px;">Invoice</h3>
                </div>
                <div id="invoice_box" style="float: left;">
                    <strong>Invoice #:</strong>&nbsp;<?php echo($invoice_info['reference']);?>
                </div>
                <div id="date_box" style="float: right;">
                    <strong>Invoice Date:</strong>&nbsp;<?php echo($invoice_info['date']);?>
                    <?php 
                        if(!$invoice_info['status']){
                            if(isset($settings['pending_invoice_delete_days'])){
                                $due_date = date("Y-m-d", strtotime($invoice_info['date']."+".(int)$settings['pending_invoice_delete_days']." days"));
                    ?>
                    <br /><strong>Due Date:</strong>&nbsp;<?php echo($due_date);?>

                    <?php
                            }
                        }
                    ?>
                </div>
                <!-- <div class="clear">&nbsp;</div> -->
                <div id="date_box" style="clear: both;" align="center">
                    <?php 
                        $invoice_status = ($invoice_info['status'] == 1) ? '<span class="ok" style="font-size: 12px; font-weight: bold;">Paid</span>':'<span class="error" style="font-size:12px; font-weight: bold;">Pending</span>';
                        echo($invoice_status);                    
                    ?>
                </div>
                <div id="customer_box" style="float: left;">
                    <div align="left">
                        <strong>Customer:</strong>
                    </div>
                    <div id="customer_info" class="package" style="width: 200px; height: 80px;">
                        <?php if($customer_info['fullname']){echo($customer_info['fullname'].'<br/>');} ?>
                        <?php if($customer_info['email']){echo($customer_info['email'].'<br/>');} ?>
                        <?php if($customer_info['address']){echo($customer_info['address'].'<br/>');} ?>
                        <?php if($customer_info['postcode']){echo($customer_info['postcode'].'<br/>');} ?>
                        <?php if($customer_info['phone']){echo($customer_info['phone'].'<br/>');} ?>
                    </div>                
                </div>
                <div id="company_box" style="float: right;">
                    <div align="left">
                        <strong>Pay To:</strong>
                    </div>
                    <div id="company_info" class="package" style="width: 200px; height: 80px;">
                        <?php echo($settings['company_name']);?><br/>
                        <?php echo($settings['email_address']);?>
                    </div>                
                </div>
                <div class="clear">&nbsp;</div>
                <div id="order_details" class="left">
                    <h3>Order Details:</h3>
                </div>
                <?php if($invoice_info['domain']){?>
                <div class="clear">&nbsp;</div>
                <div align="left" style="padding-left: 5px;">
                  <div align="left" class="left label"><span class="label">Domain:</span></div> <br />
                  <div align="left" style="padding-left: 10px;">
                     <?php echo($invoice_info['domain']);?>
                  </div>
                </div>
                <?php } ?>

                <div class="clear">&nbsp;</div>
                <div align="left" style="padding-left: 5px;">
                  <div align="left" class="left label"><span class="label">Hosting Package:</span></div> <br />
                  <div align="left" style="padding-left: 10px;">
                     <?php echo($invoice_info['desc']);?>
                     <?php 
                        if($invoice_info['order_type_id'] == 2){
                            echo("(Renewal)");
                        }
                     ?>
                  </div>
                </div>

                <div class="clear">&nbsp;</div>
                <div align="left" style="padding-left: 5px;">
                  <div align="left" class="left label"><span class="label">Amount:</span></div> <br />
                  <div align="left" style="padding-left: 10px;">
                     <?php echo(number_format((float) $invoice_info['total_amount'],2));?> <?php echo($settings['currency']);?>
                  </div>
                </div>
               <?php if($invoice_info['discount_amount'] > 0){?>
                <div class="clear">&nbsp;</div>
                <div align="left" style="padding-left: 5px;">
                  <div align="left" class="left label"><span class="label">Discount:</span></div> <br />
                  <div align="left" style="padding-left: 10px;">
                     <?php echo(number_format($invoice_info['discount_amount'],2).' '.$settings['currency'].' '. $invoice_info['discount_type']); ?>
                  </div>
                </div>
                <?php } ?>
                <div class="clear">&nbsp;</div>
                <div align="left" style="padding-left: 5px;">
                  <div align="left" class="left label"><span class="label">Total Due:</span></div> <br />
                  <div align="left" style="padding-left: 10px;">
                     <?php echo(number_format(((float) $invoice_info['total_amount'] - $invoice_info['discount_amount'] ),2));?> <?php echo($settings['currency']);?>
                  </div>
                </div>

                <div class="clear">&nbsp;</div>
                <?php if(!$invoice_info['status']){?>
                <div id="payment_option" class="clear">
                    <h3>Make Payment:</h3>
                </div>
                <div align="left" style="padding-left: 10px;">
                    Please select a payment method below:
                </div>
                <div class="clear">&nbsp;</div>
                <div align="left" style="padding-left: 10px;">
                    <span class="label">Payment Method:</span>
                    <select name="payment_option_id" id="payment_option_id" onchange="_payment_method(this.value);">
                        <?php echo($html_option);?>
                    </select>                    
                </div>
                <div class="clear">&nbsp;</div>
                <div id="payment_method_html" align="center">
                <?php 
                    if(is_array($payment_methods_html)){
                        foreach($payment_methods_html as $method_id=>$method_html){
                ?>
                    <div id="payment_method_html_<?php echo($method_id);?>" style="display:none; padding-left: 10px;">
                        <?php echo($method_html);?>
                    </div>
                <?php 
                        }
                    } 
                ?>
                <?php } ?>
                </div>
                <br />
                <div id="print_invoice" align="right" class="clear">
                    <input type="button" name="btn_submit" value="Print Invoice" class="submit" onclick="window.print();">
                </div>                
                                
            <?php } ?>
        </div>
    </div>
    </div><!-- closes flex-container -->
</div>
</body>
</html>
