<div id="timetracker-projects" class='theme-white'>
    <h1 class='page-title'>Timelines Admin</h1>

    <div id="dialog-confirm" title="Confirmation Required" class='hidden'>
        Are you sure you want to delete this timeline?
    </div>
    <div id="dialog-project-edit-form" title="Edit Project" class='hidden'>
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
    <br />
    <div id="dialog-timeline-edit-form" title="Edit Timeline" class='hidden'>
        <p class="validateTips">All form fields are required.</p>

        <form>
            <fieldset>
                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" id="edit-name-submit" tabindex="-1" style="position:absolute; top:-1000px" class='ui-button primary'>
            </fieldset>
        </form>
    </div>

    <div id="timelines">
    </div>
</div>