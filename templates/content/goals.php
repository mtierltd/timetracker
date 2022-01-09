<div id="timetracker-goals">
    <h1 class='page-title'>Goals</h1>
    <div id="client-input-container" class='center'>
        <form>
            <label for="project-select">
                Project
                <select id="project-select" style="width: 200px;">
                </select>
            </label>
            <input tabindex="1" type="text" spellcheck="false" autocomplete="off" class="" value="" placeholder="# hours" id="new-goal-hours">
            <label for="new-goal-interval">
                <select id="new-goal-interval" style="width: 200px;">
                    <option value="Weekly">Weekly</option>
                    <option value="Monthly">Monthly</option>
                </select>
            </label>
            <input type="submit" value="Add goal" id="new-goal-submit" class='ui-button primary'>
        </form>
    </div>
    <div id="dialog-confirm" title="Confirmation Required" class='hidden'>
        Are you sure you want to delete this goal?
    </div>
    <div class="clearfix"> </div>
    <div id="goals">
    </div>
</div>