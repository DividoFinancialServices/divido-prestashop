<p class="payment_module">
	<a href="{$link->getModuleLink('dividofinancing', 'payment')|escape:'html'}" title="{l s='Pay with Divido' mod='dividofinancing'}">
		<img src="{$this_path_divido}views/img/divido-logo.png" alt="{l s='Pay in instalments with Divido' mod='divido'}" width="86" />
		{l s='Pay by bank wire' mod='bankwire'}&nbsp;<span>{l s='(order processing will be longer)' mod='bankwire'}</span>
	</a>
</p>
