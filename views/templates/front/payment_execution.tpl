{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='dividofinancing'}">{l s='Checkout' mod='dividofinancing'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Bank-wire payment' mod='dividofinancing'}
{/capture}

<h1 class="page-heading">
    {l s='Pay in instalments with Divido' mod='dividofinancing'}
</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="alert alert-warning">
        {l s='Your shopping cart is empty.' mod='dividofinancing'}
    </p>
{else}
    <form action="{$link->getModuleLink('dividofinancing', 'validation', [], true)|escape:'html':'UTF-8'}" method="post">
        <div class="box cheque-box">
            <fieldset id="divido-checkout" data-divido-calculator class="divido-calculator divido-theme-blue" data-divido-amount="{$total}" data-divido-plans="{$plans}" data-divido-filter-plans="1">
                <h1>
                    <a href="https://www.divido.com" target="_blank" class="divido-logo divido-logo-sm" style="float:right;">Divido</a>
                    Pay in instalments
                </h1>
                <div style="clear:both;"></div>
                <dl>
                    <dt><span data-divido-choose-finance data-divido-label="Choose your plan" data-divido-form="divido_finance"></span></dt>
                    <dd><span class="divido-deposit" data-divido-choose-deposit data-divido-label="Choose your deposit" data-divido-form="divido_deposit"></span></dd>
                </dl>
                <div class="description">
                    <strong>
                        <span data-divido-agreement-duration></span> monthly payments of <span data-divido-monthly-instalment></span>
                    </strong>
                </div>
                <div class="divido-info">
                    <dl>
                        <dt>Term</dt>
                        <dd><span data-divido-agreement-duration></span> months</dd>
                        <dt>Monthly instalment</dt>
                        <dd><span data-divido-monthly-instalment></span></dd>
                        <dt>Deposit</dt>
                        <dd><span data-divido-deposit></span></dd>
                        <dt>Cost of credit</dt>
                        <dd><span data-divido-finance-cost-rounded></span></dd>
                        <dt>Total payable</dt>
                        <dd><span data-divido-total-payable-rounded></span></dd>
                        <dt>Total interest APR</dt>
                        <dd><span data-divido-interest-rate></span></dd>
                    </dl>
                </div>
                <div class="clear"></div>
                <p>You will be redirected to Divido to complete this finance application when you continue</p>
            </fieldset>
        </div><!-- .cheque-box -->
        <p class="cart_navigation clearfix" id="cart_navigation">
            <a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='dividofinancing'}
            </a>
            <button class="button btn btn-default button-medium" type="submit">
                <span>{l s='Continue to Divido' mod='dividofinancing'}<i class="icon-chevron-right right"></i></span>
            </button>
        </p>
    </form>
{/if}
