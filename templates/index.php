<?php
style('timetracker', 'all');
style('timetracker', 'style');
script('timetracker', $script);

?>

<div id="app-navigation">
	<?php print_unescaped($this->inc('navigation/index')); ?>
	<?php print_unescaped($this->inc('settings/index')); ?>
</div>

<div id="app-content">
	<div id="app-content-wrapper">
		<?php print_unescaped($this->inc($appPage)); ?>
	</div>
</div>
