<div id="timetracker-tags">
    <h1 class='page-title'>Tags</h1>
    <div id="client-input-container" class='center'>
        <form>
            <input tabindex="1" type="text" spellcheck="false" autocomplete="off" class="" value="" placeholder="New tag name..." id="new-tag-input">
            <input type="submit" value="Add tag" id="new-tag-submit" class='ui-button primary'>
        </form>
    </div>
    <div id="dialog-confirm" title="Confirmation Required" class='hidden'>
        Are you sure you want to delete this tag?
    </div>
    <div id="dialog-tag-edit-form" title="Edit tag" class='hidden'>
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
    <div id="tags">
    </div>
</div>