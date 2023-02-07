{*
{foreach key=index item=jsModel from=$SCRIPTS}
    <script type="{$jsModel->getType()}" src="{$jsModel->getSrc()}"></script>
{/foreach}
*}

{foreach key=index item=cssModel from=$STYLES}
    <link rel="{$cssModel->getRel()}" href="{$cssModel->getHref()}?parameter=1" type="{$cssModel->getType()}" media="{$cssModel->getMedia()}" />
{/foreach}

{literal}
    <style>
        .gh2table tbody tr:nth-child(even) {background-color:RGB(245, 245, 245);}
        .gh2table tbody tr:nth-child(odd) {}
    </style>
{/literal}

{assign var = "ytd_individual_performance_summed" value = $YTDPERFORMANCE->GetIndividualSummedBalance()}
{assign var = "ytd_begin_values" value = $YTDPERFORMANCE->GetIndividualBeginValues()}
{assign var = "ytd_end_values" value = $YTDPERFORMANCE->GetIndividualEndValues()}
{assign var = "ytd_appreciation" value = $YTDPERFORMANCE->GetIndividualCapitalAppreciation()}
{assign var = "ytd_appreciation_percent" value = $YTDPERFORMANCE->GetIndividualCapitalAppreciationPercent()}
{assign var = "ytd_twr" value = $YTDPERFORMANCE->GetIndividualTWR()}
{assign var = "ytd_performance_summed" value = $YTDPERFORMANCE->GetPerformanceSummed()}

<input type="hidden" value='{$HOLDINGSPIEVALUES}' id="holdings_values" class="holdings_values" />
{*<input type="hidden" value='{$DYNAMIC_PIE}' id="estimate_pie_values" />*}
<input type="hidden" value='{$HOLDINGSSECTORPIESTRING}' id="sector_values" class="sector_values" />

<div class="row-fluid">
    <div class="span6">
        <div class="pull-right">
            <div class="btn-toolbar" style="display:block;">
                    <span class="btn-group">
                        <button class="btn ExportReport" style="display:block;"><strong>Generate PDF</strong></button>
                    </span>
            </div>
            <form method="post" id="export">
                <input type="hidden" value='{$ACCOUNT_NUMBER}' name="account_number" id="account_number" />
                <input type="hidden" value="PortfolioInformation" name="module" />
                <input type="hidden" value="" name="pie_image" id="pie_image" />
                <input type="hidden" value="" name="sector_pie_image" id="sector_pie_image" />
                <input type="hidden" value="GH2Report" name="view" />
                <input type="hidden" value="{$ORIENTATION}" name="orientation" />
                <input type="hidden" value="1" name="pdf" />
                <input type="hidden" value="{$CALLING_RECORD}" name="calling_record" />
                <input type="hidden" value="{$START_DATE}" name="report_start_date" />
                <input type="hidden" value="{$END_DATE}" name="report_end_date" />
            </form>
        </div>
    </div>
</div>

<div class="row-fluid" style="display:block; clear:both;">
    <div id="GHReport_wrapper" style="padding-left:10%; padding-right:10%">
        <div class="GHReport_section">
            <h2 style="width:100%; background-color:lightblue; text-align:center;">PORTFOLIO SUMMARY</h2>
            <table style="width:100%">
                <tr>
                    <td style="width:50%;">
                        <table style="display:block; width:90%; font-size:16px;">
                            <thead>
                            <tr>
                                <th>&nbsp;</th>
                                <th style="font-weight:bold; text-align:right;">VALUE</th>
                                <th style="font-weight:bold; text-align:right;">ALLOC</th>
                            </tr>
                            </thead>
                            <tbody>
                            {foreach from=$HOLDINGSPIEARRAY item=v}
                                <tr>
                                    <td style="font-weight:bold; width:50%; padding-bottom:10px;"><span style="color:{$v.color}">{$v.title}</span></td>
                                    <td style="text-align:right; width:25%; padding-bottom:10px;"><span style="color:{$v.color}">${$v.value|number_format:0:".":","}</span></td>
                                    <td style="text-align:right; width:25%; padding-bottom:10px;"><span style="color:{$v.color}">{$v.percentage|number_format:2:".":","}%</span></td>
                                </tr>
                            {/foreach}
                            <tr>
                                <td>&nbsp;</td>
                                <td style="text-align:right; border-top:1px solid black; padding-top:10px;">${$GLOBALTOTAL|number_format:0:".":","}</td>
                                <td>&nbsp;</td>
                            </tr>
                            {if $YTDPERFORMANCE->GetDividendAccrualAmount() neq 0}
                                <tr>
                                    <td style="padding-top:10px; font-weight:bold;">Dividend Accrual:</td>
                                    <td style="text-align:right; padding-top:10px;">${$YTDPERFORMANCE->GetDividendAccrualAmount()|number_format:0:".":","}</td>
                                    <td>&nbsp;</td>
                                </tr>
                            {/if}
                            {if $MARGIN_BALANCE neq 0}
                                <tr>
                                    <td>
                                        <p>Margin Balance: <span style="{if $MARGIN_BALANCE lt 0}color:red;{else}color:green;{/if}">${$MARGIN_BALANCE|number_format:0}</span></p>
                                    </td>
                                </tr>
                            {/if}
                            {if $NET_CREDIT_DEBIT neq 0}
                                <tr>
                                    <td>
                                        <p>Net Credit Debit: <span style="{if $NET_CREDIT_DEBIT lt 0}color:red;{else}color:green;{/if}">${$NET_CREDIT_DEBIT|number_format:0}</span></p>
                                    </td>
                                </tr>
                            {/if}
                            {if $UNSETTLED_CASH neq 0}
                                <tr>
                                    <td>
                                        <p>Unsettled Cash: <span style="{if $UNSETTLED_CASH lt 0}color:red;{else}color:green;{/if}">${$UNSETTLED_CASH|number_format:0}</span></p>
                                    </td>
                                </tr>
                            {/if}
                            </tbody>
                        </table>
                    </td>
                    <td>
                        <div id="dynamic_pie_holder" class="dynamic_pie_holder" style="height: 400px; width:550px;"></div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="GHReport_section">
            <h2 style="width:100%; background-color:lightgrey; text-align:center;"><span style="font-size:14px;">{$HEADING} PERFORMANCE ({$YTDPERFORMANCE->GetStartDate()|date_format:'%B %d, %Y'} to {$YTDPERFORMANCE->GetEndDate()|date_format:'%B %d, %Y'})</span></h2>
            <table class="gh2table table table-bordered" style="display:block; width:100%;">
                <thead>
                <tr>
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:center">Account Number</th>
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:center">Name</th>
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:right">Beginning Balance</th>
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">Flow</th>
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">Income</th>
                    {*                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">Other</th>*}
                    {*                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:center">Expenses</th>*}
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">Ending Value</th>
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">Investment Return</th>
                    {*                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:center">Investment Gain (%)</th>*}
                    <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">TWR</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$ytd_individual_performance_summed key=account_number item=v}
                    <tr {if $ytd_individual_performance_summed[$account_number]['Flow']->disable_performance eq 1} style="{*background-color:#FFFFE0;*}" {/if}>
                        <td>**{$account_number|substr:5} ({$ytd_individual_performance_summed[$account_number]['account_type']})</td>
                        <td>{$ytd_individual_performance_summed[$account_number]['account_name']}</td>
                        <td style="text-align:right;">({$ytd_begin_values[$account_number]->date|date_format:'%m/%d/%Y'}) ${$ytd_begin_values[$account_number]->value|number_format:0:".":","}</td>
                        <td style="text-align:right;">${$ytd_individual_performance_summed[$account_number]['Flow']->amount|number_format:0:".":","}</td>
                        <td style="text-align:right;">${$ytd_individual_performance_summed[$account_number]['income_div_interest']->amount|number_format:0:".":","}</td>
                        {*<td style="text-align:right;">${$ytd_individual_performance_summed[$account_number]['Income']->amount|number_format:2:".":","}</td>*}
                        {*                    <td style="text-align:right;">${$ytd_individual_performance_summed[$account_number]['Expense']->amount|number_format:2:".":","}</td>*}
                        <td style="text-align:right;">${$ytd_end_values[$account_number]->value|number_format:0:".":","}</td>
                        <td style="text-align:right;">${$ytd_appreciation[$account_number]|number_format:0:".":","}</td>
                        {*                    <td style="text-align:right;">{$ytd_appreciation_percent[$account_number]|number_format:2:".":","}%</td>*}
                        <td style="text-align:right;">{$ytd_twr[$account_number]|number_format:2:".":","}%</td>
                    </tr>
                {/foreach}
                <tr>
                    <td style="background-color:RGB(245, 245, 245); font-weight:bold;" colspan="2">Blended Portfolio Return</td>
                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$YTDPERFORMANCE->GetBeginningValuesSummed()->value|number_format:0:".":","}</td>
                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.Flow->amount|number_format:0:".":","}</td>
                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.income_div_interest->amount|number_format:0:".":","}</td>
                    {*<td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.Income->amount|number_format:2:".":","}</td>*}
                    {*<td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.Expense->amount|number_format:2:".":","}</td>*}
                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$YTDPERFORMANCE->GetEndingValuesSummed()->value|number_format:0:".":","}</td>
                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$YTDPERFORMANCE->GetCapitalAppreciation()|number_format:0:".":","}</td>
                    {*                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">{$YTDPERFORMANCE->GetAppreciationPercent()|number_format:2:".":","}%</td>*}
                    <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">{$YTDPERFORMANCE->GetTWR()|number_format:2:".":","}%</td>
                </tr>
                <tr>
                    <td colspan="7">S&amp;P 500</td>
                    <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetIndex("GSPC")|number_format:2:".":","}%</td>
                </tr>
                <tr>
                    <td colspan="7">Barclays Aggregate Bond</td>
                    <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetIndex("AGG")|number_format:2:".":","}%</td>
                </tr>
                <tr>
                    <td colspan="7">MSCI Emerging Market index</td>
                    <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetIndex("EEM")|number_format:2:".":","}%</td>
                </tr>
                <tr>
                    <td colspan="7">MSCI EAFE Index</td>
                    <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetIndex("MSCI_EAFE")|number_format:2:".":","}%</td>
                </tr>
{*                <tr>
                    <td colspan="7" style="font-weight:bold;">Blended Benchmark Return</td>
                    <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetBenchmark()|number_format:2:".":","}%</td>
                </tr>*}
                </tbody>
            </table>
        </div>

        {*<div class="detailViewInfo row-fluid">
            <div class="contents">
                <div class="row-fluid">
                    <table class="table table-bordered DynaTable table-collapse">
                        <thead>
                        <tr>
                            <th>Symbol</th>
                            <th>Cusip</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Weight</th>
                            <th>Value</th>
                        </tr>
                        </thead>
                        <tbody>
                        {foreach from=$HOLDINGSPIEARRAY key=k item=heading}
                            <tr>
                                <td colspan="5">{$heading['title']}</td>
                                <td style="text-align:right;">{$heading['percentage']}%</td>
                                <td style="text-align:right;">${$heading['value']|number_format:2:".":","}</td>
                            </tr>
                            {foreach from=$POSITIONS key=pk item=pv}
                                {if $pv['aclass'] eq $heading['title']}
                                    <tr>
                                        <td>{$pv['symbol']}</td>
                                        <td>{$pv['cusip']}</td>
                                        <td>{$pv['security_name']}</td>
                                        <td style="text-align:right;">{$pv['quantity']}</td>
                                        <td style="text-align:right;">${$pv['price']}</td>
                                        <td style="text-align:right;">{$pv['weight']}%</td>
                                        <td style="text-align:right;">${$pv['market_value']|number_format:2:".":","}</td>
                                    </tr>
                                {/if}
                            {/foreach}
                        {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>*}
    </div>
</div>
