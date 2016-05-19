{if $status == 'ok'}
	<p>{l s='Your dredit card order from' mod='jostpay'} <span class="bold">{$shop_name}</span> {l s='has been processed.' mod='jostpay'}
		<br /><br />
        {l s='Your order reference number is: ' mod='jostpay'}{$transactionID}
        <br /><br />
        {l s='For any questions or for further information, please contact our' mod='jostpay'} <a href="{$base_dir_ssl}contact-form.php">{l s='customer support' mod='jostpay'}</a>.
	</p>
{else}
	<p class="warning">
		{l s='We encountered a problem processing your order. If you think this is an error, you can contact our' mod='jostpay'} 
		<a href="{$base_dir_ssl}contact-form.php">{l s='customer support department who will be pleased to assist you.' mod='jostpay'}</a>.
	</p>
{/if}
