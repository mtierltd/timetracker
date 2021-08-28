<ul class="with-icon">
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.page.index'));?>" class='nav-icon-timer svg'>Timer</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.dashboard.index'));?>" class='nav-icon-dashboard svg'>Dashboard</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.goals.index'));?>" class='nav-icon-goals svg'>Goals</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.reports.index'));?>"" class='nav-icon-reports svg'>Reports</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.timelines.index'));?>"" class='nav-icon-reports svg'>Timelines</a></li>
	<?php if (\OC_User::isAdminUser(\OC_User::getUser())): ?>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.timelinesAdmin.index'));?>"" class='nav-icon-reports svg'>Timelines Admin</a></li>
	<?php endif; ?>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.projects.index'));?>" class='nav-icon-projects svg'>Projects</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.clients.index'));?>" class='nav-icon-clients svg'>Clients</a></li>
	<li><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('timetracker.tags.index'));?>" class='nav-icon-tags svg'>Tags</a></li>
</ul>
