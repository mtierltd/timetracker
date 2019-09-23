<ul>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.page.index'));?>" class='nav-icon-recent svg'>Timer</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.dashboard.index'));?>" class='nav-icon-dashboard'>Dashboard</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.reports.index'));?>"" class='nav-icon-reports'>Reports</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.timelines.index'));?>"" class='nav-icon-reports'>Timelines</a></li>
	<?php if (\OC_User::isAdminUser(\OC_User::getUser())): ?>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.timelinesAdmin.index'));?>"" class='nav-icon-reports'>Timelines Admin</a></li>
	<?php endif; ?>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.projects.index'));?>" class='nav-icon-projects'>Projects</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.clients.index'));?>" class='nav-icon-clients'>Clients</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.tags.index'));?>" class='nav-icon-tags'>Tags</a></li>

</ul>
