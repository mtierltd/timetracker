<div id="timetracker-content">
<div id="top-work-bar">
    <div id="work-input-container">
        <form>
            <input tabindex="1" type="text" spellcheck="false" autocomplete="off" class="" value="" placeholder="What have you done?" id="work-input">
        </form>
    </div>
    <div id="top-work-bar-right">
    <!-- <input type="text" id="datepicker-from">
    <input type="text" id="datepicker-to"> -->
    <div id="timer"></div>
    <button id="start-tracking" class="ui-button ui-widget ui-corner-all ui-button-icon-only" title="Start">
        <span class="my-icon play-button"></span>
    </button>
    </div>

</div>
<div id="dialog-confirm" title="Confirmation Required" class='hidden'>
  Are you sure you want to delete this work item?
</div>
<div id="dialog-work-item-edit-form" title="Edit work item" class='hidden'>
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
<div class="clearfix"> </div>
<div id="work-intervals">
</div>
</div>