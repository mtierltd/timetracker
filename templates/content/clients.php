<div id="timetracker-clients">
    <h1 class='page-title'>Clients</h1>
    <div id="client-input-container" class='center'>
        <form>
            <input tabindex="1" type="text" spellcheck="false" autocomplete="off" class="" value="" placeholder="New client name..." id="new-client-input">
            <input type="submit" value="Add client" id="new-client-submit" class="ui-button primary">
        </form>
    </div>
    <div id="dialog-confirm" title="Confirmation Required" class='hidden'>
        Are you sure you want to delete this client?
    </div>
    <div id="dialog-client-edit-form" title="Edit client" class='hidden'>
        <p class="validateTips">All form fields are required.</p>

        <form>
            <fieldset>
                <label for="name">Name</label>
                <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">

                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
            </fieldset>
        </form>
    </div>
    <div class="clearfix"> </div>
    <div id="clients">
    </div>
</div>