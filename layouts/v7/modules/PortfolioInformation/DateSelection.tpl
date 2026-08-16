{if $OVERVIEW_STYLE eq 1}
    <div id="dateselection">
        <table class="dateselectiontable">
            <tr>
                <td>
                    <input type="text" id="select_end_date" value="{$END_DATE}" style="display:block; margin-left:5px; margin-right:5px;" />
                </td>
                <td>
                    <input type="button" id="calculate_report" value="Calculate" style="display:block; margin-left:5px; font-size:15px;" />
                </td>
                {if $SHOW_POSITIONS_TOGGLE eq 1}
                <td>
                    <label style="display:inline-block; margin-left: 10px; font-weight: normal; margin-bottom: 0px; cursor: pointer;">
                        <input type="checkbox" id="show_positions" {if $SHOW_POSITIONS eq 1}checked{/if} style="margin-top: -3px;" /> Show Positions
                    </label>
                </td>
                {/if}
            </tr>
        </table>
    </div>
{else}
    <div id="dateselection">
        <table class="dateselectiontable">
            <tr>
                <td>
                    <select id="report_date_selection" style="border:2px solid black; margin-right:5px;">
                        {foreach key=index item=option from=$DATE_OPTIONS}
                            <option value="{$option.option_value}" data-start_date="{$option.date.start}" data-end_date="{$option.date.end}" {if $option.default eq 1} selected {/if}>{$option.option_name}</option>
                        {/foreach}
                    </select>
                </td>
                {if $SHOW_START_DATE EQ 1}
                    <td>
                        <input type="text" id="select_start_date" value="{$START_DATE}" style="display:block; margin-right:5px;" />
                    </td>
                {/if}
                {if $SHOW_END_DATE EQ 1}
                    <td>
                        <input type="text" id="select_end_date" value="{$END_DATE}" style="display:block; margin-left:5px; margin-right:5px;" />
                    </td>
                {/if}
                <td>
                    <input type="button" id="calculate_report" value="Calculate" style="display:block; margin-left:5px; font-size:15px;" />
                </td>
                {if $SHOW_POSITIONS_TOGGLE eq 1}
                <td>
                    <label style="display:inline-block; margin-left: 10px; font-weight: normal; margin-bottom: 0px; cursor: pointer;">
                        <input type="checkbox" id="show_positions" {if $SHOW_POSITIONS eq 1}checked{/if} style="margin-top: -3px;" /> Show Positions
                    </label>
                </td>
                {/if}
            </tr>
        </table>
    </div>
{/if}
