<?php $urlGenerator = \OC::$server->get(\OCP\IURLGenerator::class); ?>
<ul class="with-icon">
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.page.index'));?>" class='nav-icon-timer svg'>Timer</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.dashboard.index'));?>" class='nav-icon-dashboard svg'>Dashboard</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.goals.index'));?>" class='nav-icon-goals svg'>Goals</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.reports.index'));?>" class='nav-icon-reports svg'>Reports</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.timelines.index'));?>" class='nav-icon-reports svg'>Timelines</a></li>
	<?php if (\OC_User::isAdminUser(\OC_User::getUser())): ?>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.timelinesAdmin.index'));?>" class='nav-icon-reports svg'>Timelines Admin</a></li>
	<?php endif; ?>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.projects.index'));?>" class='nav-icon-projects svg'>Projects</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.clients.index'));?>" class='nav-icon-clients svg'>Clients</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.tags.index'));?>" class='nav-icon-tags svg'>Tags</a></li>
	<li><a href="<?php p($urlGenerator->linkToRoute('timetracker.page.spa'));?>" class='nav-icon-settings svg'>SPA Preview (WIP)</a></li>
</ul>
