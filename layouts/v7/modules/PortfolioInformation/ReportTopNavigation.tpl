{foreach key=index item=cssModel from=$STYLES}
	<link rel="{$cssModel->getRel()}" href="{$cssModel->getHref()}" type="{$cssModel->getType()}" media="{$cssModel->getMedia()}" />
{/foreach}
<div class="ReportTopNavigation {if $HIDE_WIDGET eq 1}hide_widget{/if}">
    <table class="table table-bordered listViewEntriesTable">
    	<thead>
			<tr class="listViewHeaders">
				<th>Account Selection</th>
				<th>Account Number</td>
				<th style="text-align:left;">Contact Name</th>
				<th style="text-align:left;">Account Type</th>
				<th style="text-align:right;">Total</th>
{*				<th style="text-align:right;">Market (Balance)</th>
				<th style="text-align:right;">Cash (Balance)</th>
				<th style="text-align:right;">Money Market Value</th>
				<th style="text-align:right;">Management Fees</th>*}
				{if not $HIDE_LINKS}
					<th>Nickname</th>
					<th>Last Viewed</th>
					<th colspan="2">&nbsp;</th>
				{/if}
			</tr>
		</thead>

		<tbody>

			{foreach from=$SUMMARY_INFO key=k item=i}
				<tr class="listViewEntries">
					<td><input type="checkbox" class="account_select" value="{$i.account_number}" /></td>
					{if not $HIDE_LINKS}
						<td><a onclick="return false;" class="loadReport" href="#">{$i.account_number} <input type='hidden' class='acct_number' value='{$i.account_number}' /></a></td>
					{else}
<!--                                <td><a href="index.php?module={$CALLING_MODULE}&view=Detail&record={$RECORD}&mode=showDetailViewByMode&requestMode=full&tab_label=Household Details&autoload=1&account_load={$i.account_number}">{$i.account_number}</a></td>-->
{*						<td><a href="index.php?module={$CALLING_MODULE}&relatedModule=PortfolioInformation&view=Detail&record={$RECORD}&mode=showRelatedList&tab_label=Reports&autoload=1&account_load={$i.account_number}">{$i.account_number}</a></td>*}
						<td><a class='context-menu-one_DISABLED' data-acc="{$i.account_number}" href="index.php?module=PortfolioInformation&view=Detail&record={$i.portfolioinformationid}" target="_blank">{$i.account_number}</a></td>
					{/if}
					<td style="text-align:left;"><a href="index.php?module=Contacts&view=Detail&record={$i.contactid}" target="_blank">{$i.firstname} {$i.lastname}</a></td>
					<td style="text-align:left;">{$i.cf_2549}</td>
					<td style="text-align:right;">${$i.total_value|number_format:2}</td>
{*					<td style="text-align:right;">${$i.money_market_funds|number_format:2}</td>
{*					<td style="text-align:right;">${$i.securities|number_format:2}</td>
					<td style="text-align:right;">${$i.cash|number_format:2}</td>
					<td style="text-align:right; color:green; font-weight:bold;" >${$i.management_fee|number_format:2}</td>*}
					{if not $HIDE_LINKS}
						<td>{$i.nickname}</td>
						<td>{$i.last_update}</td>
						<td colspan="2"><input type="button" value="Edit" class="btn edit_account" data-edit="{$i.record}"/></td>
					{/if}
				</tr>
			{/foreach}
			<tr class="listViewEntries">
				<td><strong>Totals:</strong></td>
				<td style="text-align:right;" colspan="3">${$GRANDTOTALS.total_value|number_format:2}</td>
{*				<td style="text-align:right;">${$GRANDTOTALS.money_market_value|number_format:2}</td>
				<td style="text-align:right;">${$GRANDTOTALS.market_value|number_format:2}</td>
                <td style="text-align:right;">${$GRANDTOTALS.cash_value|number_format:2}</td>
				<td style="text-align:right; color:green; font-weight:bold;">${$GRANDTOTALS.management_fee|number_format:2}</td>*}
				{if not $HIDE_LINKS}
					<td colspan="4">&nbsp;</td>
				{/if}
			</tr>
		</tbody>
    </table>
</div>
<tr>
{foreach key=index item=jsModel from=$SCRIPTS}
    <script type="{$jsModel->getType()}" src="{$jsModel->getSrc()}"></script>
{/foreach}
<div class="ReportTop"></div>
<div class="ReportBottom"></div>
