
<div id="timetracker-projects" class='theme-white'>
<h1 class='page-title'>Timelines</h1>

<div id="dialog-confirm" title="Confirmation Required" class='hidden'>
  Are you sure you want to delete this project?
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
<br/>
<div class="time-controls">
  <div id="report-range" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; width: 100%">
      <i class="fa fa-calendar"></i>&nbsp;
      <span></span> <i class="fa fa-caret-down"></i>
  </div>
</div>
<br/>
<div class="group-controls">
  <label for="group1">
    Group by
      <select id="group1" style="width: 200px;">>
          <option value="project">Project</option>
          <option value="client">Client</option>
      </select>
  </label>
  <label for="group2">
    and by
      <select id="group2" style="width: 200px;">
          <option value="">None</option>  
          <option value="name">Time Entry</option>
      </select>
  </label>
      <select id="group3" style="width: 200px;">
          <option value="day">Daily</option>
          <option value="week">Weekly</option>
          <option value="month">Monthly</option>
          <option value="year">Yearly</option>
      </select>
        <!--<button id="download-xlsx">Download XLSX</button>
        <button id="download-pdf">Download PDF</button> -->
</div>
<div class="filter-controls">
  <label for="filter-project">
      Filter projects
    <span id="filter-project" style="width: 200px;">
    </span>
  </label>
  <label for="filter-client">
      Filter clients
    <span id="filter-client" style="width: 200px;">
    </span>
  </label>
</div>
<div class="table-controls">
      <button id="timeline-csv">Export Timeline CSV</button>
      <!-- <button id="download-json">Download JSON</button> -->
      <!--<button id="download-xlsx">Download XLSX</button> -->
      <!-- <button id="download-pdf">Download PDF</button> -->
</div>

<div id="report">
</div>
<br/>
<h1 class='page-title'>Exported Timelines Statuses</h1>
<br/>
<div id="timelines">
</div>
</div>