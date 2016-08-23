{$test}
<div id="product-divido" class="panel product-tab">
	<input type="hidden" name="submitted_tabs[]" value="Divido" />
	<h3>{l s='Divido product settings'}</h3>

	<div class="form-group">
		<label class="control-label col-lg-3" for="uploadable_files">
			<span class="label-tooltip" data-toggle="tooltip"
				title="{l s='Use global defaults or product specific settings'}">
				{l s='Shown plans'}
			</span>
		</label>
		<div class="col-lg-3">
			<select name="prod_plans_option" id="prod_plans_option" ">
                <option value="0" {if $prod_plans_option == 0}selected="selected"{/if}>{l s='Global defaults'}</option>
                <option value="1" {if $prod_plans_option == 1}selected="selected"{/if}>{l s='Custom plans'}</option>
            </select>
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-lg-3" for="text_fields">{l s='Available plans'}</label>
		<div class="col-lg-3">
			<select multiple="multiple" name="prod_plans[]" id="prod_plans" value="{$prod_plans|htmlentities}">
            {foreach from=$allPlans key=id item=name}
                <option value="{$id}" {if $id|in_array:$prod_plans}selected="selected"{/if}>{$name}</option>
            {/foreach}
            </select>
		</div>
	</div>
	<div class="panel-footer">
		<a href="{$link->getAdminLink('AdminProducts')|escape:'html':'UTF-8'}{if isset($smarty.request.page) && $smarty.request.page > 1}&amp;submitFilterproduct={$smarty.request.page|intval}{/if}" class="btn btn-default"><i class="process-icon-cancel"></i> {l s='Cancel'}</a>
		<button type="submit" name="submitAddproduct" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> {l s='Save'}</button>
		<button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> {l s='Save and stay'}</button>
	</div>
</div>
