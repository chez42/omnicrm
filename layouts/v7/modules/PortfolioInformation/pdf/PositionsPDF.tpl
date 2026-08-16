<div style="display: block; margin: 0px auto 10px auto; width: 100%">
    <h2 style="padding: 5px; background-color: lightblue; text-align: center; font-size: 16px;">PORTFOLIO POSITIONS</h2>
</div>    
<table class="table table-bordered" style="display: table; width: 100%; font-size: 11px; border-collapse: collapse;" cellpadding="4">
    <thead>
        <tr style="background-color: #999; color: white; font-weight: bold;">
            <th align="left" width="10%">Symbol</th>
            <th align="left" width="15%">Cusip</th>
            <th align="left" width="40%">Description</th>
            <th align="right" width="10%">Quantity</th>
            <th align="right" width="10%">Price</th>
            <th align="right" width="5%">Weight</th>
            <th align="right" width="10%">Value</th>
        </tr>
    </thead>
    <tbody>
    {foreach from=$HOLDINGSPIEARRAY key=k item=heading}
        <tr style="background-color: #e5e5e5; font-weight: bold;">
            <td colspan="5" align="left"><strong>{$heading['title']}</strong></td>
            <td align="right"><strong>{$heading['percentage']|number_format:2:".":","}%</strong></td>
            <td align="right"><strong>${$heading['value']|number_format:2:".":","}</strong></td>
        </tr>
        {foreach from=$POSITIONS key=pk item=pv}
            {if $pv['aclass'] eq $heading['title']}
                <tr style="background-color: #fff;">
                    <td align="left">&nbsp;&nbsp;{$pv['symbol']}</td>
                    <td align="left">{$pv['cusip']}</td>
                    <td align="left">{$pv['security_name']}</td>
                    <td align="right">{$pv['quantity']|number_format:2:".":","}</td>
                    <td align="right">${$pv['price']|number_format:2:".":","}</td>
                    <td align="right">{$pv['weight']|number_format:2:".":","}%</td>
                    <td align="right">${$pv['market_value']|number_format:2:".":","}</td>
                </tr>
            {/if}
        {/foreach}
    {/foreach}
    </tbody>
</table>
