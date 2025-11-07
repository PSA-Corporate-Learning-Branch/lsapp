<?php
require('inc/lsapp.php');

if(isAdmin()):
if($_POST):

if($_POST['action'] == 'delete'):

	$currentuser = LOGGED_IN_IDIR;
	$idir = $_POST['idir'];
	$f = fopen('data/people.csv','r');
	$temp_table = fopen('data/people-temp.csv','w');
	$headers = fgetcsv($f);
	fputcsv($temp_table,$headers);
	while (($data = fgetcsv($f)) !== FALSE){
		if($data[0] == $idir && $currentuser != $data[0]) {
				continue;
		} 
		fputcsv($temp_table,$data,',');
	}
	
	fclose($f);
	fclose($temp_table);

	rename('data/people-temp.csv','data/people.csv');
	header('Location: /lsapp/people.php');
	

elseif($_POST['action'] == 'add'):

	//IDIR,Role,Name,Email,Status,Phone,Title,Super,Manager,Pronouns,Colors,iStore,kepler
	$fromform = $_POST;
	$pronouns = $fromform['Pronouns'] ? $fromform['Pronouns'] : 'Unspecified';
	$newadmin = Array(strtolower(h($fromform['idir'])),
					h($fromform['role']), 	// Team
					h($fromform['name']),
					h($fromform['email']),
					'Active',				// Status
					h($fromform['phone']),
					h($fromform['title']),
					0,						// Super
					'',						// Manager
					$pronouns,
					'0|50|50|50|50',		// Colors
					0,						// iStore
					0						// Kepler
		);

	$admin = array($newadmin);
	$fp = fopen('data/people.csv', 'a+');
	foreach ($admin as $fields) {
		fputcsv($fp, $fields);
	}
	fclose($fp);
	header('Location: /lsapp/people.php');
	
endif;
endif;

else:
	echo "Say no go...";
endif; // isAdmin()