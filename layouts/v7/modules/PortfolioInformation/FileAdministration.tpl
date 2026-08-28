<div id="custodian-wrapper">
    <div id="file-locations">
        <h2>File Parsing (GLOBAL)</h2>
        <p>This table represents each rep code in the system and where its files are stored.  The system knows where to put the files
        based on the custodian and Rep Code to determine the location.  Active means that the rep code should be getting a daily feed on it.
        The daily email report listing missing files depends on this table to be accurate, or deactivated rep code's will show up as
        having missing files</p>
        <button id="add-row">Add New RepCode</button>
        Number Of Days To Parse: <input type="text" class="num_days" value="7" />
        <div id="file-locations-table"></div>
    </div>
    <div id="custodian-interactions">
        <h2>CRM and Custodian Interactions (THIS INSTANCE ONLY)</h2>
        <p>Current Status: <span class="current-status">Idle</span></p>
        <p>Parsing Status: <span class="parse-status">Idle</span></p>
        <p>Calculation Status: <span class="calculation-status">Idle</span></p>
        <input type="button" id="PullRecalculate" value="Pull and Recalculate" title="Pull all data from custodian and recalculate homepage values.  (Runs the DataPull.service cron job)" />
        <input type="button" id="RecalculateHomepageWidgets" value="Recalculate Homepage Widgets" title="Recalculate the homepage widgets to update balances and AUM" />
        <input type="text" id="consolidateDays" placeholder="<--Number of days to reconcile" /><br />
        <input type="button" id="ClearReconciledTransactions" value="Clear Reconciled Transactions" title="Clear Reconciled Transactions" />
    </div>
</div>
{*
<table class="FileLocationsTable" style="table-layout:fixed; width:1000px;">
    <thead>
    <tr>
        <td>ID</td>
        <td>Custodian</td>
        <td>Tenant</td>
        <td>File Location</td>
        <td>Rep Code</td>
        <td>Master Rep Code</td>
        <td>Omni Code</td>
        <td>Prefix</td>
        <td>Suffix</td>
    </tr>
    </thead>
    <tbody>
        {foreach from=$LOCATIONS item=v}
            <tr>
                <td><input type="text" style="width:100px;" value="{$v.id}" name="id" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.custodian}" name="custodian" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.tenant}" name="tenant" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.file_location}" name="file_location" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.rep_code}" name="rep_code" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.master_rep_code}" name="master_rep_code" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.omni_code}" name="omni_code" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.prefix}" name="prefix" data-id="{$v.id}" /></td>
                <td><input type="text" style="width:100px;" value="{$v.suffix}" name="suffix" data-id="{$v.id}" /></td>
            </tr>
        {/foreach}
    </tbody>
</table>

<input type="button" class="addrow" value="Add New Location" />

{foreach key=index item=jsModel from=$SCRIPTS}
    <script type="{$jsModel->getType()}" src="{$jsModel->getSrc()}"></script>
{/foreach}
*}