{literal}
    <style>
        .gh2table tbody tr td:nth-child(even) {background-color:RGB(245, 245, 245);}
        .gh2table tbody tr td:nth-child(odd) {}
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
<input type="hidden" value='{$HOLDINGSSECTORPIESTRING}' id="sector_values" class="sector_values" />

<div id="GHReport_wrapper">
    <div class="GHReport_section">
        <h2 style="width:100%; background-color:lightblue; text-align:center;">PORTFOLIO SUMMARY</h2>
        <table style="width:100%;">
            <tr>
                <td style="width:50%;">
                    <table style="display:block; width:90%; font-size:14px;">
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
                        {if $MARGIN_BALANCE neq 0}
                            <tr>
                                <td colspan="3">
                                    <p>Margin Balance: <span style="{if $MARGIN_BALANCE lt 0}color:red;{else}color:green;{/if}">${$MARGIN_BALANCE|number_format:0}</span></p>
                                </td>
                            </tr>
                        {/if}
                        {if $NET_CREDIT_DEBIT neq 0}
                            <tr>
                                <td colspan="3">
                                    <p>Net Credit Debit: <span style="{if $NET_CREDIT_DEBIT lt 0}color:red;{else}color:green;{/if}">${$NET_CREDIT_DEBIT|number_format:0}</span></p>
                                </td>
                            </tr>
                        {/if}
                        {if $UNSETTLED_CASH neq 0}
                            <tr>
                                <td colspan="3">
                                    <p>Unsettled Cash: <span style="{if $UNSETTLED_CASH lt 0}color:red;{else}color:green;{/if}">${$UNSETTLED_CASH|number_format:0}</span></p>
                                </td>
                            </tr>
                        {/if}
                        </tbody>
                    </table>
                </td>
                <td style="padding-left:100pt;">
                    {$PIE_IMAGE}
                </td>
            </tr>

        </table>
    </div>
    <div class="GHReport_section">
        <h2 style="width:100%; background-color:lightgrey; text-align:center; font-size:16px; padding:5px;"><span style="font-size:16px;">{$HEADING} PERFORMANCE ({$YTDPERFORMANCE->GetStartDate()|date_format:'%B %d, %Y'} to {$YTDPERFORMANCE->GetEndDate()|date_format:'%B %d, %Y'})</span></h2>
        <table class="table table-bordered" style="display:block; width:100%; font-size:14px;">
            <thead>
            <tr>
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:center">Account Number</th>
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:center">Name</th>
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:right; padding-right:5px;">Beginning<br />Balance</th>
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right; padding-right:5px;">Flow</th>
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right; padding-right:5px;">Income</th>
                {*<th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right">Other</th>*}
                {*<th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:center">Expenses</th>*}
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right; padding-right:5px;">Ending<br />Value</th>
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right; padding-right:5px;">Investment<br />Return</th>
                {*                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:center">Investment Gain (%)</th>*}
                <th style="font-weight:bold; background-color:RGB(245, 245, 245); width:5%; text-align:right; padding-right:5px;">TWR</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$ytd_individual_performance_summed key=account_number item=v}
                <tr {if $ytd_individual_performance_summed[$account_number]['Flow']->disable_performance eq 1} style="{*background-color:#FFFFE0;*}" {/if}>
                    <td style="width:10%;">**{$account_number|substr:5} ({$ytd_individual_performance_summed[$account_number]['account_type']})</td>
                    <td style="width:10%;">{$ytd_individual_performance_summed[$account_number]['account_name']}</td>
                    <td style="text-align:right; width:10%; padding-right:5px;">({$ytd_begin_values[$account_number]->date|date_format:'%m/%d/%Y'}) ${$ytd_begin_values[$account_number]->value|number_format:0:".":","}</td>
                    <td style="text-align:right; width:10%; padding-right:5px;">${$ytd_individual_performance_summed[$account_number]['Flow']->amount|number_format:0:".":","}</td>
                    <td style="text-align:right; width:10%; padding-right:5px;">${$ytd_individual_performance_summed[$account_number]['income_div_interest']->amount|number_format:0:".":","}</td>
                    {*<td style="text-align:right; width:10%">${$ytd_individual_performance_summed[$account_number]['Income']->amount|number_format:0:".":","}</td>*}
                    {*<td style="text-align:right;">${$ytd_individual_performance_summed[$account_number]['Expense']->amount|number_format:0:".":","}</td>*}
                    <td style="text-align:right; width:10%; padding-right:5px;">${$ytd_end_values[$account_number]->value|number_format:0:".":","}</td>
                    <td style="text-align:right; width:10%; padding-right:5px;">${$ytd_appreciation[$account_number]|number_format:0:".":","}</td>
                    {*                    <td style="text-align:right;">{$ytd_appreciation_percent[$account_number]|number_format:0:".":","}%</td>*}
                    <td style="text-align:right; width:10%; padding-right:5px;">{$ytd_twr[$account_number]|number_format:2:".":","}%</td>
                </tr>
            {/foreach}
            <tr>
                <td style="background-color:RGB(245, 245, 245); font-weight:bold;" colspan="2">Blended Portfolio Return</td>
                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$YTDPERFORMANCE->GetBeginningValuesSummed()->value|number_format:0:".":","}</td>
                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.Flow->amount|number_format:0:".":","}</td>
                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.income_div_interest->amount|number_format:0:".":","}</td>
                {*<td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.Income->amount|number_format:0:".":","}</td>*}
                {*<td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$ytd_performance_summed.Expense->amount|number_format:0:".":","}</td>*}
                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$YTDPERFORMANCE->GetEndingValuesSummed()->value|number_format:0:".":","}</td>
                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">${$YTDPERFORMANCE->GetCapitalAppreciation()|number_format:0:".":","}</td>
                {*                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">{$YTDPERFORMANCE->GetAppreciationPercent()|number_format:0:".":","}%</td>*}
                <td style="text-align:right; background-color:RGB(245, 245, 245); font-weight:bold;">{$YTDPERFORMANCE->GetTWR()|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td colspan="7" style="padding-top:25px;">S&amp;P 500</td>
                <td style="text-align:right; font-weight:bold; padding-top:25px;">{$YTDPERFORMANCE->GetIndex("GSPC")|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td colspan="7" style="background-color:RGB(245,245,245)">Barclays Aggregate Bond</td>
                <td style="text-align:right; font-weight:bold; background-color:RGB(245,245,245);">{$YTDPERFORMANCE->GetIndex("AGG")|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td colspan="7" style="">MSCI Emerging Market index</td>
                <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetIndex("EEM")|number_format:2:".":","}%</td>
            </tr>
{*            <tr>
                <td colspan="7" style="background-color:RGB(245,245,245)">MSCI EAFE Index</td>
                <td style="text-align:right; font-weight:bold; background-color:RGB(245,245,245)">{$YTDPERFORMANCE->GetIndex("MSCIEAFE")|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td colspan="7" style="font-weight:bold;">Blended Benchmark Return</td>
                <td style="text-align:right; font-weight:bold;">{$YTDPERFORMANCE->GetBenchmark()|number_format:2:".":","}%</td>
            </tr>*}
            </tbody>
        </table>
    </div>
</div>
