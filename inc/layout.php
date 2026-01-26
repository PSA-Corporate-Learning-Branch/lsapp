<?php
//
// Layout functions for LSApp
// 

function getHeader() {
	
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<!--<link rel="manifest" href="/lsapp/manifest.json" crossorigin="use-credentials">-->
<link rel="icon" href="/lsapp/favicon.ico">
<meta name="author" content="Allan Haggett <allan.haggett@gov.bc.ca>">
<?php
	
}
function getScripts() {
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="/lsapp/css/rome.min.css">
<!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.css"> -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.skiplink {
  background: #003366;
  color: #fff;
  left: 50%;
  padding: 4px;
  position: absolute;
  transform: translateY(-100%);
}

.skiplink:focus {
  transform: translateY(0%);
}
/*.navbar {
	background: url('img/branding-border-colors.png') center repeat-x;
}*/
.profilelink, .profilelink:hover { color: #FFF }
h1 {
	font-size: 200%;
	font-weight: 400;
}
h2 {
	font-size: 160%;
	font-weight: 400;
}
h3 {
	font-size: 140%;
	font-weight: 400;	
}
a {
	text-decoration: none;
	/*color: #0f6cbf;*/
}

img {
	height: auto;
	max-width: 100%;
}
.cancelled,
.cancelled td,
.cancelled a {
	color: #999999;
	text-decoration: line-through;
}

.cancelled a:hover {
	color: #b1b1b1ff;
}

.fc-time{
   display : none;
}
/* Images and icons */
img,
iframe {
    height: auto;
    max-width: 100%;
}

.icon {
    width: 2rem;
    height: 100%;
    margin: 0;
}

.icon-square {
    width: 2.75rem;
    height: 3rem;
}

.icon-svg {
    display: inline-flex;
    align-self: center;
}

.icon-svg.baseline-svg svg {
    top: .125em;
    position: relative;
    overflow: visible;
}

.icon-svg svg {
    height: 1em;
    width: 1em;
    margin-right: 0.5rem;
}

#footer .navbar-brand { display: none; }

</style>
<!-- <script src='js/qrcode.js'></script> -->

<script>
/*!
 * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
 * Copyright 2011-2024 The Bootstrap Authors
 * Licensed under the Creative Commons Attribution 3.0 Unported License.
 */
(() => {
  'use strict'

  const getStoredTheme = () => localStorage.getItem('theme')
  const setStoredTheme = theme => localStorage.setItem('theme', theme)

  const getPreferredTheme = () => {
    const storedTheme = getStoredTheme()
    if (storedTheme) {
      return storedTheme
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }

  const setTheme = theme => {
    if (theme === 'auto') {
      document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
    } else {
      document.documentElement.setAttribute('data-bs-theme', theme)
    }
  }

  setTheme(getPreferredTheme())

  const showActiveTheme = (theme, focus = false) => {
    const themeSwitcher = document.querySelector('#bd-theme') 

    if (!themeSwitcher) {
      return
    }

    const themeSwitcherText = document.querySelector('#bd-theme-text')
    const activeThemeIcon = document.querySelector('.theme-icon-active')
    const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`)
    const iconOfActiveBtn = btnToActive.querySelector('i').className

    document.querySelectorAll('[data-bs-theme-value]').forEach(element => {
      element.classList.remove('active')
      element.setAttribute('aria-pressed', 'false')
    })

    btnToActive.classList.add('active')
    btnToActive.setAttribute('aria-pressed', 'true')
    activeThemeIcon.querySelector('i').className = iconOfActiveBtn


    const themeSwitcherLabel = `${themeSwitcherText.textContent} (${btnToActive.dataset.bsThemeValue})`
    themeSwitcher.setAttribute('aria-label', themeSwitcherLabel)

    if (focus) {
      themeSwitcher.focus()
    }
  }

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const storedTheme = getStoredTheme()
    if (storedTheme !== 'light' && storedTheme !== 'dark') {
      setTheme(getPreferredTheme())
    }
  })

  window.addEventListener('DOMContentLoaded', () => {
    showActiveTheme(getPreferredTheme())

    document.querySelectorAll('[data-bs-theme-value]')
      .forEach(toggle => {
        toggle.addEventListener('click', () => {
          const theme = toggle.getAttribute('data-bs-theme-value')
          setStoredTheme(theme)
          setTheme(theme)
          showActiveTheme(theme)
        })
      })
  })
})();
</script>
</head>

<?php
}


function getNavigation($context = NULL) {

$teams = getTeams();


	
?>

<?php if($context != 'footer'): ?>
<a href="#contentstart" class="skiplink">Skip to content</a>
<?php endif ?>
<nav class="navbar navbar-expand-lg bg-dark-subtle sticky-top mb-3">
  <div class="container-fluid">
  <a class="navbar-brand fw-bold" href="/lsapp/dashboard.php" title="Learning Support Application">LSApp</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
	<?php if(canAccess()): ?>
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
	  <li class="nav-item">
			<a class="nav-link" href="/lsapp/dashboard.php">Dashboard</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" href="/lsapp/">Upcoming</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" href="/lsapp/classes-past.php">Past</a>
		</li>
    <li class="nav-item dropdown">
			<a class="nav-link dropdown-toggle" href="#" id="teamsdrop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				Courses 
			</a>
			<div class="dropdown-menu" aria-labelledby="teamsdrop">
				<a class="dropdown-item" href="/lsapp/courses.php?sort=dateadded">Course Catalog</a>
				<a class="dropdown-item" href="/lsapp/course-change/index.php">Course Changes</a>
				<a class="dropdown-item" href="/lsapp/course-change/guidance-manage.php">Course Change Guidance</a>
      </div>
    </li>
    <!-- <li class="nav-item">
			<a class="nav-link" href="/lsapp/partners/">Partners</a>
		</li> -->
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="peopledrop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        Colleagues
      </a>
      <div class="dropdown-menu" aria-labelledby="peopledrop">
        <a class="dropdown-item" href="/lsapp/people.php"><strong>All People</strong></a>
        <hr class="dropdown-divider">
        <a class="dropdown-item" href="/lsapp/teams-all.php"><strong>CLB Teams</strong></a>
        <?php foreach($teams as $teamId => $teamDeets): ?>
          <?php if($teamDeets['isBranch'] == 0 || $teamDeets['name'] == 'Executive Director') continue; ?>
          <a class="dropdown-item" href="/lsapp/teams-all.php?team=<?= $teamId ?>"><?= $teamDeets['name'] ?></a>
        <?php endforeach; ?>
        <hr class="dropdown-divider">
        <a class="dropdown-item" href="/lsapp/partners/"><strong>Partners</strong></a>
        <a class="dropdown-item" href="/lsapp/partners/dashboard.php">Partner Admin Dashboard</a>
        <a class="dropdown-item" href="/lsapp/partners-development/">Development Partners</a>
      </div>
    </li>
		<li class="nav-item dropdown">
			<a class="nav-link dropdown-toggle" href="#" id="teamsdrop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				More 
			</a>
			<div class="dropdown-menu" aria-labelledby="teamsdrop">
				<a class="dropdown-item" href="https://bcgov.sharepoint.com/teams/00440" target="_blank">Corporate Learning SharePoint</a>
				<a class="dropdown-item" href="/lsapp/function-map.php">Functions</a>
				<a class="dropdown-item" href="/lsapp/kepler.php">Kepler Server Access</a>
        <a class="dropdown-item" href="/lsapp/newsletters/">Newsletter Manager</a>
				<a class="dropdown-item" href="/lsapp/audits.php">Resource Reviews</a>
				<a class="dropdown-item" href="/lsapp/snowplow.php">Snowplow (GDX) Analytics</a>
				<a class="dropdown-item" href="/lsapp/venues.php">Venues</a>
				<a class="dropdown-item" href="/lsapp/video-embedding-guide.php">Video Embedding Guide</a>
				<!-- Depricated -->
        <!-- <a class="dropdown-item" href="/lsapp/course-changes.php">All Course Changes</a> -->
				<!-- <a class="dropdown-item" href="/lsapp/kiosk/">Kiosk</a> -->
			</div>
		</li>
		
		<?php if(isAdmin()): ?>
		<li class="nav-item dropdown">
			<a class="nav-link dropdown-toggle" href="#" id="admindrop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				Admin
			</a>
			<div class="dropdown-menu" aria-labelledby="admindrop">
			
				<a class="dropdown-item" href="/lsapp/admin.php">Admin Dashboard</a>
				<a class="dropdown-item" href="/lsapp/shipping-outgoing.php">Shipping Dashboard</a>
				<a class="dropdown-item" href="/lsapp/venues-dashboard.php">Venues Dashboard</a>
				<a class="dropdown-item" href="/lsapp/materials.php">Materials Dashboard</a>
				<a class="dropdown-item" href="/lsapp/av-dashboard.php">AV Dashboard</a>
				<a class="dropdown-item" href="/lsapp/elm-sync-upload.php">Learning System Synchronize</a>
				<a class="dropdown-item" href="/lsapp/course-feed/">LearningHUB Synchronize</a>
				<a class="dropdown-item" href="/lsapp/elm-audit.php">Learning System Audit</a>
				<a class="dropdown-item" href="/lsapp/open-access-code-manager.php">Open Access Code Manager</a>
				<!-- <a class="dropdown-item" href="/lsapp/ondeck.php">On Deck for Tomorrow</a> -->
				<a class="dropdown-item" href="/lsapp/external-mailing-list.php">Weekly Stats Update</a>
				
				<a class="dropdown-item" href="/lsapp/export.php">Export</a>
				
				
				<!-- <a class="dropdown-item" href="/learning/coursework/">Coursework Landing Pages</a> -->
				
			</div>
		</li>
		<?php endif ?>		
		<?php if($context != 'footer'): ?>
		<li class="nav-item dropdown">
            <button class="btn btn-link nav-link ml-3 py-2 px-0 px-lg-2 dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static" aria-label="Toggle theme (dark)">
              <span class="theme-icon-active"><i class="me-2"></i></span>
              <span class="d-none ms-2" id="bd-theme-text">Toggle theme</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme-text">
              <li>
                <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
                  <i class="bi bi-sun-fill me-2" data-icon="bi-sun-fill"></i>
                  Light
                  <i class="bi bi-check2 d-none" data-icon="check2"></i>
                </button>
              </li>
              <li>
                <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="dark" aria-pressed="true">
                  <i class="bi bi-moon-stars-fill me-2" data-icon="bi-moon-stars-fill"></i>
                  Dark
                  <i class="bi bi-check2 d-none" data-icon="check2"></i>
                </button>
              </li>
              <li>
                <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="auto" aria-pressed="false">
                  <i class="bi bi-circle-half me-2" data-icon="bi-circle-half"></i>
                  Auto
                  <i class="bi bi-check2 d-none" data-icon="check2"></i>
                </button>
              </li>
            </ul>
        </li>
        <?php endif; // not the footer ?>
		    <?php endif; // canAccess? ?>
      </ul>
	  <?php if(canAccess() && $context != 'footer'): ?>

	Welcome,&nbsp;<a class="nav-link ml-1" href="/lsapp/person.php?idir=<?= LOGGED_IN_IDIR ?>"><?= LOGGED_IN_IDIR ?></a>. <?php echo date('D M dS') ?>&nbsp; <span id="clock"></span>
	<a role="button" class="btn btn-sm btn-primary ms-3" href="/lsapp/requests.php">Requests</a>
	<?php endif ?>
    </div>
  </div>
</nav>

<?php if($context != 'footer'): ?>
<div id="contentstart"></div>
<?php endif ?>
<?php

	
}
