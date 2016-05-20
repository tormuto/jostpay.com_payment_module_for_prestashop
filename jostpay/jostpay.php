<?php
ini_set('display_errors',1);
ini_set('error_reporting',E_ALL);
define('JOSTPAY_MERCHANT_ID','0000');

class JostPay extends PaymentModule
{
	private $_postErrors = array();

	function __construct()
	{
		$this->name = 'jostpay';
		$this->tab = 'payment';
		if(defined('_PS_VERSION_')&&_PS_VERSION_>'1.4')$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->author = 'JOSTPAY LIMITED';
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		$this->need_instance = 0;
		//$this->ps_versions_compliancy = array('min' => '1.4', 'max' => _PS_VERSION_);
		
		
		$this->controllers = array('payment', 'validation');
		$this->is_eu_compatible = 1;
		
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		

        parent::__construct();

        /* The parent construct is required for translations */
		$this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Mastercard, Visacard, Verve, Perfect Money & Bitcoin (JostPay)').$this->tab;
        $this->description = $this->l('Accepts payments from Perfectmoney, Bitcoin, Mastercard, Visacard, Verve Card (via jostpay.com)');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall ?');
		//if(!Configuration::get('JOSTPAY')) $this->warning = $this->l('No name provided');
		if (!sizeof(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module');
		
	}
	
	function install()
	{
		if(defined('_PS_VERSION_')&&_PS_VERSION_>'1.4')
		{
			if(Shop::isFeatureActive()) Shop::setContext(Shop::CONTEXT_ALL);
		}
		
		if (!parent::install() || 
		    !Configuration::updateValue('JOSTPAY_MERCHANT_ID', JOSTPAY_MERCHANT_ID) ||
			!$this->registerHook('payment')
			)
			return false;
			

		$sql="CREATE TABLE IF NOT EXISTS "._DB_PREFIX_."jostpay(
				id INT NOT NULL AUTO_INCREMENT,PRIMARY KEY(id),
				cart_id INT NOT NULL,UNIQUE(cart_id),
				date_time DATETIME NOT NULL,
				transaction_id VARCHAR(48) NOT NULL,
				customer_email VARCHAR(128) NOT NULL,
				response_description VARCHAR(225) NOT NULL,
				response_code TINYINT(1) NOT NULL,
				transaction_amount DOUBLE NOT NULL,
				customer_id INT
				)";
		
		Db::getInstance()->execute($sql);
		return true;
	}

	function uninstall()
	{
		if (!parent::uninstall() ||
		    !Configuration::deleteByName('JOSTPAY_MERCHANT_ID')
			)
			return false;
		$sql="DROP TABLE IF EXISTS "._DB_PREFIX_."jostpay";
		Db::getInstance()->execute($sql);
		return true;
	}

	private function _postValidation()
	{
		// Validate the configuration screen in the Back Office
	}

	private function _postProcess()
	{
		// Called after validated configuration screen submit in Back Office
	}

	public function displayJostPay()
	{
		$this->_html .= '
		<img src="../modules/jostpay/jostpay.gif" style="float:left; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by JostPay.').'</b><br /><br />
		'.$this->l('If the client chooses this payment mode, your JostPay account will be automatically credited.').'<br />
		'.$this->l('You need to configure your JostPay account first before using this module.').'
		<br /><br /><br />';
	}

	public function displayFormSettings()
	{
		$conf = Configuration::getMultiple(array('JOSTPAY_MERCHANT_ID'));
		$merchant_id = array_key_exists('merchant_id', $_POST) ? $_POST['merchant_id'] : (array_key_exists('JOSTPAY_MERCHANT_ID', $conf) ? $conf['JOSTPAY_MERCHANT_ID'] : '');
		
		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
		<fieldset>
			<legend><img src="../img/admin/contact.gif" />'.$this->l('Settings').'</legend>
			<label>'.$this->l('JostPay Merchant ID').'</label>
			<div class="margin-form"><input type="text" size="25" name="merchant_id" value="'.$merchant_id.'" required /></div>
			<br /><center><input type="submit" name="submitJostPay" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form><br /><br />
		';
	}

	public function getContent()
	{
		$this->_html = '<h2>JostPay Web Payments - JostPay.com</h2>';
		if (isset($_POST['submitJostPay']))
		{
			if (empty($_POST['merchant_id']))
				$this->_postErrors[] = $this->l('JostPay Merchant ID is required.');
			if (!sizeof($this->_postErrors))
			{
				Configuration::updateValue('JOSTPAY_MERCHANT_ID', $_POST['merchant_id']);
				$this->_html .= '
							<div class="conf confirm">
								<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
								'.$this->l('Settings updated').'
							</div>';
			}
			else
				$this->displayErrors();
		}

		$this->displayJostPay();
		$this->displayFormSettings();
		return $this->_html;
	}

	
	/*
		Register this transaction at JostPay and redirect to the payment page.
	*/
	function execPayment($cart)
	{
		global $cookie, $smarty;
		
		$conf = Configuration::getMultiple(array('JOSTPAY_MERCHANT_ID','PS_SHOP_NAME'));
		$invoice=new Address($cart->id_address_invoice);
		$customer = new Customer($cart->id_customer);
		$currency=new Currency($cookie->id_currency);
		$time=time();
		$cart_id=$cart->id;
		
		$resp_str='';
		
		$cresults = Db::getInstance()->ExecuteS("SELECT * FROM "._DB_PREFIX_."jostpay WHERE cart_id='$cart_id' LIMIT 1");
		if(!empty($cresults))
		{
			$crow=$cresults[0];
			if($crow['response_code']==1)$resp_str="This cart $cart_id has already been processed.";
			else Db::getInstance()->execute("DELETE FROM "._DB_PREFIX_."jostpay WHERE cart_id='$cart_id' LIMIT 1");
		}
		
		if($resp_str=='')
		{
				$date_time=date('Y-m-d H:i:s',$time);
				$total=number_format($cart->getOrderTotal(true, 3), 2, '.', '');
				$customer_id=$cart->id_customer; //$invoice->firstname, $invoice->lastname
				$sql="INSERT INTO "._DB_PREFIX_."jostpay(cart_id,transaction_id,date_time,transaction_amount,customer_email,customer_id,response_code) 
				VALUES ('$cart_id','$time','$date_time','$total','".addslashes($customer->email)."','$customer_id','0')";
				$db_ins=Db::getInstance()->execute($sql);
					
				//	public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown',$message = null, $extra_vars = array(), 
				//$currency_special = null, $dont_touch_amount = false,$secure_key = false, Shop $shop = null)
				
				if(!$db_ins)$resp_str="Error; storing jostpay transaction record in database.";
				else 
				{
					$merchant_id=$conf['JOSTPAY_MERCHANT_ID'];
					$payment_memo=$conf['PS_SHOP_NAME'].' Payment';
					
					$pending_status=Configuration::get('PS_OS_PREPARATION');
					$info="Transaction Id $response. Pending completion at JostPay";
					@$this->validateOrder($cart->id, $pending_status, $total, $this->displayName, $info);
					//$order = new Order($this->currentOrder);
					
					$return_url=(Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/validation.php';
					$history_url=(Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/jostpay_transactions.php';
		
					$resp_str="<form action='https://jostpay.com/sci' method='post' id='jostpay_payment_form'>
					<input type='hidden' name='amount' value='$total' />
					<input type='hidden' name='merchant' value='$merchant_id' />
					<input type='hidden' name='ref' value='$time' />
					<input type='hidden' name='memo' value=\"$payment_memo\" />
					<input type='hidden' name='notification_url' value='$return_url' />
					<input type='hidden' name='success_url' value='$return_url' />
					<input type='hidden' name='cancel_url' value='$return_url' />
					<button class='btn btn-lg btn-success'>If you are not automatically redirected, please click this button</button>
					</form>";
					
					echo "<!DOCTYPE html><head><title>Redirecting to JostPay</title></head><body>$resp_str<script type='text/javascript'>document.getElementById('jostpay_payment_form').submit();</script></body></html>";
					exit;
				}
			}
			/*
			$smarty->assign(array(
					"response"=>$resp_str
				));
			return $this->display(__FILE__, 'payment_execution.tpl');
			*/
			$home_url=(Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__;
			
		echo "<!DOCTYPE html><head><title>Payment Error</title></head><body><h3>$resp_str</h3><a href='$home_url'>Go back home</a></body></html>";
	}

	function hookPayment($params)
	{
		global $smarty;
		//count as well be
		//http://christheritagebc.org/presta/index.php?fc=module&module=bankwire&controller=payment
		
		$smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/'
            ));
		return $this->display(__FILE__, 'payment.tpl');
	}	
}
?>