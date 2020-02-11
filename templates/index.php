<?php
script('timetracker', "kingtable");
script('timetracker', "moment.min");
script('timetracker', "daterangepicker.min");
script('timetracker', "tabulator");
script('timetracker', "Chart.min");
//script('timetracker', "select2");
style('timetracker', "kingtable");
style('timetracker', "daterangepicker");
style('timetracker', "tabulator");

//style('timetracker', "select2");

//style('files', 'files');
style('timetracker', 'all');
style('timetracker', 'style');
script('timetracker', $script);

?>

<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
		<?php print_unescaped($this->inc('settings/index')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper">
			<?php print_unescaped($this->inc($appPage)); ?>
		</div>
	</div>
</div>

