<div id="timetracker-content">
    <div id="top-work-bar">
        <div id="work-input-container">
            <form id="work-input-form">
                <input tabindex="1" type="text" spellcheck="false" autocomplete="off" class="" value="" placeholder="What have you done?" id="work-input">
            </form>
        </div>
        <div id="top-work-bar-right">
            <div id="timer"></div>
            <button id="start-tracking" class="ui-button ui-widget ui-corner-all ui-button-icon-only" title="Start">
                <span class="my-icon play-button"></span>
            </button>
        </div>

    </div>
    <div class="ui-button ui-widget ui-corner-all" id="manual-entry-button">Manual entry</div>
    <div id="dialog-confirm" title="Confirmation Required" class='hidden'>
        Are you sure you want to delete this work item?
    </div>
    <div id="dialog-work-item-edit-form" title="Edit work item" class='hidden'>
        <p class="validateTips">All form fields are required.</p>

        <form>
            <fieldset>
                <label for="name">Name</label>
                <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                <div class="clear"></div>
                <label for="details">Details</label>
                <textarea style='vertical-align: middle;width:300px;' name="details" id="details" cols="40" rows="5" value="" id="details" class="text ui-widget-content ui-corner-all"></textarea>

                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
            </fieldset>
        </form>
    </div>

    <div id="dialog-manual-entry" title="Add work item" class='hidden'>
        <p class="validateTips">All form fields are required.</p>

        <form id='form-manual-entry'>
            <fieldset>
                <label for="name">Name</label>
                <input type="text" name="name" id="name-manual-entry" value="" class="text ui-widget-content ui-corner-all">
                <div class="clear"></div>
                <label for="details">Details</label>
                <textarea style='vertical-align: middle;width:250px;' name="details" cols="40" rows="5" value="" id="details-manual-entry" class="text ui-widget-content ui-corner-all"></textarea>
                <!-- <div id='hours-manual-entry'>&nbsp;</div> -->
                <label for="hours">Interval</label>
                <input type="text" name="hours" id="hours-manual-entry" value="" class="text ui-widget-content ui-corner-all">
                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
            </fieldset>
        </form>
    </div>

    <div class="clearfix"> </div>
    <br />
    <div class="time-controls">
        <div id="report-range" style="cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; width: 100%">
            <i class="fa fa-calendar"></i>&nbsp;
            <span></span> <i class="fa fa-caret-down"></i>
        </div>
    </div>
    <br />

    <div class="clearfix"> </div>
    <div id="work-intervals">
    </div>
</div>
