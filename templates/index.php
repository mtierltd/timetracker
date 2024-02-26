<?php

$nextcloudMajorVersion = \OCP\Util::getVersion()[0];

style('timetracker', 'all');
style('timetracker', 'style');

if (intval($nextcloudMajorVersion) >= 28) {
	style('timetracker', 'style-compat');
}

script('timetracker', $script);

?>

<div id="app-navigation">
	<?php print_unescaped($this->inc('navigation/index')); ?>
</div>

<div id="app-content">
	<div id="app-content-wrapper">
		<?php print_unescaped($this->inc($appPage)); ?>
	</div>
</div>
