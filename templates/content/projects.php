
<div id="timetracker-projects" class='theme-white'>
<h1 class='page-title'>Projects</h1>
<div id="client-input-container" class='center'>
        <form>
            <input tabindex="1" type="text" spellcheck="false" autocomplete="off" class="" value="" placeholder="New project name..." id="new-project-input">
            <div class="form-control" id="client-select"></div>
            <input type="submit" value="Add project" id="new-project-submit" class='ui-button primary'>
        </form>
</div>
<div id="project-filter-container" class='left'>
        
        <div class="checkbox">
          <label>
            <input id='show-archived-projects' type="checkbox" value="">
            Show archived projects
          </label>
        </div>
        
</div>

<div id="dialog-confirm" title="Confirmation Required" class='hidden'>
  Are you sure you want to delete this project?
</div>
<div id="dialog-project-edit-form" title="Edit Project" class='hidden'>
  <p class="validateTips">All form fields are required.</p>
 
  <form>
    <fieldset>
      <div>
        <label for="name">Name</label>
        <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
      </div>
      <div class='hidden admin-only' id='locked-div'>
        <label for="locked">Locked</label>
        <input type="checkbox" name="locked" id="locked" value="" class="text ui-widget-content ui-corner-all">
      </div>
      <div class='' id='archived-div'>
        <label for="archived">Archived</label>
        <input type="checkbox" name="archived" id="archived" value="" class="text ui-widget-content ui-corner-all">
      </div>
      <div class='hidden' id='locked-options'>
      <label for="locked-select-tags">Allowed Tags</label>
        <div class="form-control" id="locked-select-tags"></div>
        <label for="locked-select-users">Users</label>
        <div class="form-control" id="locked-select-users"></div>
      </div>
      <div>
      <label for="client-select-popup">Client</label>
      <div class="form-control" id="client-select-popup"></div>
      </div>
      <!-- Allow form submission with keyboard without duplicating the dialog button -->
      <input type="submit" id="edit-name-submit" tabindex="-1" style="position:absolute; top:-1000px" class='ui-button primary'>
    </fieldset>
  </form>
</div>
<div class="clearfix"> </div>

<div id="projects">
</div>
</div>