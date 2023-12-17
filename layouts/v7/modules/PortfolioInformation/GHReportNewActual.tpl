{foreach key=index item=cssModel from=$STYLES}
    <link rel="{$cssModel->getRel()}" href="{$cssModel->getHref()}?parameter=1" type="{$cssModel->getType()}" media="{$cssModel->getMedia()}" />
{/foreach}

{assign var = "ytd_individual_performance_summed" value = $YTDPERFORMANCE->GetIndividualSummedBalance()}
{assign var = "ytd_begin_values" value = $YTDPERFORMANCE->GetIndividualBeginValues()}
{assign var = "ytd_end_values" value = $YTDPERFORMANCE->GetIndividualEndValues()}
{assign var = "ytd_appreciation" value = $YTDPERFORMANCE->GetIndividualCapitalAppreciation()}
{assign var = "ytd_appreciation_percent" value = $YTDPERFORMANCE->GetIndividualCapitalAppreciationPercent()}
{assign var = "ytd_twr" value = $YTDPERFORMANCE->GetIndividualTWR()}
{assign var = "ytd_performance_summed" value = $YTDPERFORMANCE->GetPerformanceSummed()}

{literal}
    <style>
        .ghperformancetable tbody tr:nth-child(even) {background-color:RGB(245, 245, 245);}
        .ghperformancetable tbody tr:nth-child(odd) {}
        .ghperformancetable tbody tr td {width:100%;}
    </style>
{/literal}

<input type="hidden" value='{$HOLDINGSPIEVALUES}' id="holdings_values" class="holdings_values" />
<input type="hidden" value='{$DYNAMIC_PIE}' id="estimate_pie_values" />

<div class="row-fluid ReportTitle detailViewTitle">
    <div class=" span12 ">
        <div class="row-fluid">
            <div class="span6">
                <div class="row-fluid">
                    <span class="recordLabel font-x-x-large textOverflowEllipsis span pushDown"><span></span>&nbsp;</span>
                </div>
            </div>
            <div class="span6">
                <div class="pull-right">
                    <form method="post" id="export">
                        <input type="hidden" value='{$ACCOUNT_NUMBER}' name="account_number" id="account_number" />
                        <input type="hidden" value="PortfolioInformation" name="module" />
                        <input type="hidden" value="" name="pie_image" id="pie_image" />
                        <input type="hidden" value=" name="view" />
                        <input type="hidden" value="{$ORIENTATION}" name="orientation" />
                        <input type="hidden" value="1" name="pdf" />
                        <input type="hidden" value="{$CALLING_RECORD}" name="calling_record" />
                        <input type="hidden" value="{$START_DATE}" name="report_start_date" />
                        <input type="hidden" value="{$END_DATE}" name="report_end_date" />
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
{*
<div id="index_selection">
    <a href="index.php?module=PortfolioInformation&view=Indexes">Change Indexes</a>
</div>
*}
<div id="index_selection">
    <a href="index.php?module=PortfolioInformation&view=Statements" target="_blank">Statement Settings</a>
    {*    <a href="#" id="statement_settings">Statement Settings</a>*}
</div>

<div id="GHReport_wrapper" class="GHReport_UI_Wrapper">
    <table id="GHReport_header" style="font-family:Calibri, Sans-Serif;">
        <tr>
            <td style="width:65%; vertical-align: top; border">
                {if $LOGO neq ''}<img class="pdf_crm_logo" src="{$LOGO}" style="width:60%;" />{/if}
            </td>
            {*            <td style="width:50%; text-align:center;"><h1>{$PREPARED_FOR}</h1></td>*}
            <td style="width:30%; font-size: 9pt;">
                {if $PREPARED_BY eq null}
                    {$USER_DATA['first_name']} {$USER_DATA['last_name']}<br />
                    {if $USER_DATA['title'] neq ''}{$USER_DATA['title']}<br />{/if}
                    {if $USER_DATA['email1'] neq ''}{$USER_DATA['email1']}<br />{/if}
                    {if $USER_DATA['phone_work'] neq ''}{$USER_DATA['phone_work']}{/if}
                {else}
                    {$PREPARED_BY}
                {/if}
                <div class="btn-toolbar" style="float:right;">
                    <span class="btn-group">
                        <button class="btn ExportReport" style="background-color:#36A645; border-radius:5px; box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);"><strong>Print PDF</strong></button>
                    </span>
                </div>
            </td>
        </tr>
    </table>
    <p style="margin:0; padding:0; font-size:9pt;"><span style="color:RGB(0,32,96); font-weight:bold; font-size:10pt;">{$PREPARED_FOR}</span><br />Prepared: {$PREPARE_DATE}</p>

    <div class="GHReport_section">
        <h2 class="blue_header">PLAN GOALS AND ASSUMPTIONS</h2>
        <p>{$POLICY|nl2br}</p>
        <p>Report Notes:</p>
        <textarea rows="5" name="personal_notes" form="export" class="boxsizingborder" style="font-size:14pt;"></textarea>
    </div>
    <div class="GHReport_section">
        <h2 class="blue_header">PORTFOLIO SUMMARY</h2>
        <table style="width:100%" border="0">
            <tr>
                <td style="width:50%;">
                    <table style="display:block; width:90%; font-size:14px;"  border="0">
                        <thead>
                        <tr>
                            <th style="font-weight:bold; text-align:left; padding:0;">ASSET CLASS</th>
                            <th style="font-weight:bold; text-align:right; padding:0;" class="borderBottom">VALUE</th>
                            <th style="font-weight:bold; text-align:right; padding:0;" class="borderBottom">ALLOC</th>
                        </tr>
                        </thead>
                        <tbody>
                        {foreach from=$HOLDINGSPIEARRAY item=v}
                            <tr>
                                <td style="font-weight:bold; width:50%; padding-bottom:2px;">{$v.title}</td>
                                <td style="text-align:right; width:25%;">${$v.value|number_format:0:".":","}</td>
                                <td style="text-align:right; width:25%;">{$v.percentage|number_format:2:".":","}%</td>
                            </tr>
                        {/foreach}
                        <tr>
                            <td>&nbsp;</td>
                            <td style="text-align:right;" class="borderTop borderBottom">${$GLOBALTOTAL|number_format:0:".":","}</td>
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
                        {if $YTDPERFORMANCE->GetDividendAccrualAmount() neq 0}
                            <tr>
                                <td style="padding-top:10px; font-weight:bold;">Dividend Accrual:</td>
                                <td style="text-align:right; padding-top:10px;">${$YTDPERFORMANCE->GetDividendAccrualAmount()|number_format:0:".":","}</td>
                                <td>&nbsp;</td>
                            </tr>
                        {/if}
                        </tbody>
                    </table>
                </td>
                <td>
                    <div id="dynamic_pie_holder" class="dynamic_pie_holder" style="height: 300px;"></div>
                </td>
            </tr>
        </table>
    </div>
    <div class="GHReport_section">
        <h2 class="grey_header"><span style="font-size:20px;">{$HEADING} PERFORMANCE ({$YTDPERFORMANCE->GetStartDate()|date_format:'%B %d, %Y'} to {$YTDPERFORMANCE->GetEndDate()|date_format:'%B %d, %Y'})</span></h2>
        <table class='table' style="font-family:Calibri, Sans-Serif;">
            <thead>
            <tr style="background-color:RGB(245, 245, 245);">
                <th style="font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:left; text-decoration:underline;">ACCOUNT NAME</th>
                <th style="font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:12%; text-align:left; text-decoration:underline;">ACCT TYPE</th>
                <th style="font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:10%; text-align:left; text-decoration:underline;">ACCT NUMBER</th>
                <th style="margin:0; padding:0; font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:right; text-decoration:underline;">BEG.<br />BALANCE</th>
                <th style="margin:0; padding:0; font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:right; text-decoration:underline;">ADDTNS/<br />WTHDRWLS</th>
                <th style="margin:0; padding:0; font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:right; text-decoration:underline;">CHANGE IN<br />VALUE</th>
                <th style="margin:0; padding:0; font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:15%; text-align:right; text-decoration:underline; padding-right:2%;">END<br />BALANCE</th>
                <th style="margin:0; padding:0; font-size: 8pt; font-weight:bold; background-color:RGB(245, 245, 245); width:10%; text-align:right; text-decoration:underline;">INCOME</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$ytd_individual_performance_summed key=account_number item=v}
                <tr {if $ytd_individual_performance_summed[$account_number]['Flow']->disable_performance eq 1} style="" {/if}>
                    <td style="font-size: 8pt; margin:0; padding:0;">{$ytd_individual_performance_summed[$account_number]['account_name']}</td>
                    <td style="font-size: 8pt; margin:0; padding:0;">{$ytd_individual_performance_summed[$account_number]['account_type']}</td>
                    <td style="font-size: 8pt; margin:0; padding:0;">**{$account_number|substr:5}</td>
                    {*<td style="font-size: 8pt; margin:0; padding:0;">$</td>*}
                    <td style="font-size: 8pt; text-align:right; margin:0; padding:0;">${$ytd_begin_values[$account_number]->value|number_format:0:".":","}</td>
                    {*                    <td style="font-size: 8pt; margin:0; padding:0;">$</td>*}
                    <td style="font-size: 8pt; text-align:right; margin:0; padding:0;">${$ytd_individual_performance_summed[$account_number]['Flow']->amount|number_format:0:".":","}</td>
                    {*<td style="font-size: 8pt; margin:0; padding:0;">$</td>*}
                    <td style="font-size: 8pt; text-align:right; margin:0; padding:0;">${$ytd_individual_performance_summed[$account_number]['change_in_value']|number_format:0:".":","}</td>
                    {*<td style="font-size: 8pt; margin:0; padding:0;">$</td>*}
                    <td style="font-size: 8pt; text-align:right; margin:0; padding:0; padding-right:2%;">${$ytd_end_values[$account_number]->value|number_format:0:".":","}</td>
                    {*<td style="font-size: 8pt; margin:0; padding:0;">$</td>*}
                    {*                    <td style="font-size: 8pt; text-align:right; margin:0; padding:0;">{$ytd_individual_performance_summed[$account_number]['income_div_interest']->amount|number_format:0:".":","}</td>*}
                    <td style="font-size: 8pt; text-align:right; margin:0; padding:0;">${$ytd_individual_performance_summed[$account_number]['income_div_interest']->amount|number_format:0:".":","}</td>
                </tr>
            {/foreach}
            <tr>
                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;" colspan="3">&nbsp;</td>
                {*                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;">$</td>*}
                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; text-align:right; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;"><span style="text-align:right;">${$YTDPERFORMANCE->GetBeginningValuesSummed()->value|number_format:0:".":","}</span></td>
                {*                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;">$</td>*}
                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; text-align:right; border-top:1px solid black; border-bottom: 1px double;">${$ytd_performance_summed.Flow->amount|number_format:0:".":","}</td>
                {*                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;">$</td>*}
                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; text-align:right; border-top:1px solid black; border-bottom: 1px double;">${$ytd_performance_summed.change_in_value|number_format:0:".":","}</td>
                {*                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;">$</td>*}
                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; text-align:right; border-top:1px solid black; border-bottom: 1px double; padding-right:2%;">${$YTDPERFORMANCE->GetEndingValuesSummed()->value|number_format:0:".":","}</td>
                {*                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; border-top:1px solid black; border-bottom: 1px double;">$</td>*}
                {*                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; text-align:right; border-top:1px solid black; border-bottom: 1px double;">{$ytd_performance_summed.income_div_interest->amount|number_format:0:".":","}</td>*}
                <td style="margin:0; padding:0; font-size: 8pt; font-weight:bold; text-align:right; border-top:1px solid black; border-bottom: 1px double;">${$ytd_performance_summed.income_div_interest->amount|number_format:0:".":","}</td>
            </tr>
            </tbody>
        </table>
    </div>
    <div class="GHReport_section">
        <h2 class="blue_header">Performance Metrics</h2>
        <table class="table ghperformancetable" style="display:block; width:100%; font-family:Calibri, Sans-Serif;" border="0">
            <thead>
            <tr>
                <td colspan="2" style="font-weight:bold; background-color:RGB(245, 245, 245); text-align:left; text-decoration:underline; font-size:10pt;">PORTFOLIO PERFORMANCE</td>
                {*                <td colspan="2" style="font-weight:bold; background-color:RGB(245, 245, 245); text-align:left; text-decoration:underline;">BENCHMARK PERFORMANCE</td>*}
            </tr>
            </thead>
            <tbody>
            <tr>
                <td colspan="2" style="text-align:right; text-decoration:underline; font-size:8pt;">{$YTDPERFORMANCE->GetStartDateMDY()}-{$YTDPERFORMANCE->GetEndDateMDY()}</td>
            </tr>
            <tr>
                <td style="font-weight:bold; font-size:8pt; color:#33256C;">Combined Portfolio Return (TWR)</td>
                <td style="color:#33256C; text-align:right; font-weight:bold; font-size:8pt; padding-right:40pt;">{$YTDPERFORMANCE->GetTWR()|number_format:2:".":","}%</td>
            </tr>
            {*                <tr>
                                <td style="font-weight:bold; font-size:8pt; color:#33256C;">Blended Benchmark</td>
                                <td style="color:#33256C; text-align:right; font-size:8pt; padding-right:40pt;">{$YTDPERFORMANCE->GetBenchmark()|number_format:2:".":","}%</td>
                            </tr>*}
            <tr>
                <td style="font-size:8pt;">NASDAQ US Dividend Achievers Select</td>
                <td style="text-align:right; font-size:8pt; padding-right:40pt;">{$YTDPERFORMANCE->GetIndex("DVG")|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td style="font-size:8pt;">S&amp;P 500</td>
                <td style="text-align:right; font-size:8pt; padding-right:40pt;">{$YTDPERFORMANCE->GetIndex("GSPC")|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td style="font-size:8pt;">S&P 500 Bond Index</td>
                <td style="text-align:right; font-size:8pt; padding-right:40pt;">{$YTDPERFORMANCE->GetIndex("SP500BDT")|number_format:2:".":","}%</td>
            </tr>
            <tr>
                <td style="font-size:8pt;">ICE U.S Treasury Core Bond TR Index</td>
                <td style="text-align:right; font-size:8pt; padding-right:40pt;">{$YTDPERFORMANCE->GetIndex("IDCOTCTR")|number_format:2:".":","}%</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
