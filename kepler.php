<?php 
require('inc/lsapp.php');
$idir = LOGGED_IN_IDIR;
$person = getPerson($idir);
$keplerpeople = getKeplerPeople();
$istorepeople = getiStoreDesignees();
?>

<?php getHeader() ?>

<title>Kepler Access Control</title>

<?php getScripts() ?>

<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<div class="col-md-4">

    <h1>Access to Kepler</h1>
    
    <?php
    $directory = '/';  // You can specify any directory
    $free_space = disk_free_space($directory);

    echo '<div class="alert alert-success">Available disk space: ' . formatSizeUnits($free_space) . '</div>';

    function formatSizeUnits($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
    ?>

    <p>
        Kepler is the name of the web server that hosts much of the 
        course content that the Corporate Learning Branch is repsonsible for. 
        Kepler is a Windows-based IIS server that is split into two parts: 
    </p>
        
    <ol>
        <li>
            A set of directories under \Learning folder that reside at 
            https://gww.bcpublicservice.gov.bc.ca/learning/ <strong>Accessing this address requires
            an IDIR to access</strong>. <a href="kepler-large-file-finder.php" class="fw-bold">Large File Finder.</a>
            NOTE: Large File Finder can take a while to load and it resource intensive for the server to load.
            Use with caution.
        </li>
        <li>
            A set of directories under \NonSSOLearning that are accessible via 
            https://learn.bcpublicservice.gov.bc.ca/ <strong>This address is publicly accessible</strong>.
        </li>
    </ol>
    <p>Kepler is managed by the 
        <a href="https://insider.gww.gov.bc.ca/corporate-services/">
            Corporate Services Division
        </a>.
    </p>

    <h2>Mapping the Drive</h2>

    <ul>
        <li>\\Kepler.dmz\Learning</li>
        <li>\\Kepler.dmz\NonSSOLearning</li>
    </ul>

</div>

<div class="col-md-4">

    <h2>Currently</h2>
    <p>These people currently have full write access to the Kepler server as part of the 
        security group: PSA_w_ELM_prod_C</p>

    <ul>
        <?php foreach($keplerpeople as $p): ?>
            <li><a href="/lsapp/person.php?idir=<?= $p[0] ?>"><?= $p[2] ?></a></li>
        <?php endforeach ?>
    </ul>

    <p>These people also have access (as of 2024-03-26), but are not Corporate Learning staff:</p>
    <ul>
        <li>James Avery - TES ELM Systems support</li>
        <li>Mandeep Sidhu - PSA Recruitment Support Clerk, HR Ops</li>
    </ul>

    <!-- 
    Original list provided by Corp. Services 2023-03-22:
    <li>Avery, James
    <li>Dzenkiw, Randy
    <li>Haggett, Allan
    <li>Lane, Richard
    <li>McQuinn, Nancy
    <li>Mitchell, Shannon
    <li>Nielsen, Kristine
    <li>Novak, Ben
    <li>Pendray, Juliet
    <li>Price, Jeff
    <li>Sandor, David
    <li>Sidhu, Mandeep
    <li>Sinclair, Nori
    <li>Stewart, Ory
    <li>Swoveland, Britt -->

</div>

<div class="col-md-4">
    
    <h2>New Request</h2>
    <p>Corporate Learning designated iStore submitters may make requests 
        through a permissible portal.</p>
    <p><em>Any Director may also submit these requests via email to the “PSA IT 
        Service Request” email address for new people to gain “write access” to 
        Kepler folders using the following template.</em></p>

    <p>Designated iStore Submitter<?= count($istorepeople) > 1 ? 's' : '' ?>:</p>
        <ul class="mb-4">
        <?php foreach($istorepeople as $p): ?>
            <li><a href="/lsapp/person.php?idir=<?= $p[0] ?>"><?= $p[2] ?></a>
        <?php endforeach ?>
        </ul>
        

    <div>
        <a class="btn btn-block btn-primary mb-4" href="mailto:ItServiceRequest@gov.bc.ca?BCC=Corporatelearning.admin@gov.bc.ca&body=Dear IT Service Request Team,%0D%0DPlease add:%0D%0D<?= $person[2] ?> (<?= $person[0] ?>) <?= $person[3] ?> to security group PSA_w_ELM_prod_C on Kepler.%0D%0DThank you,%0D%0DThe Corporate Learning Branch&subject=Request: Add user to PSA_w_ELM_prod_C on Kepler">
            Send New Request
        </a>
    </div>

    <div class="alert alert-warning">If the button doesn't work for you, please copy the following text into a 
        new email manually and send it to ItServiceRequest@gov.bc.ca</div>

<label for="template">Request Template</label>
<textarea name="template" id="template" class="form-control" cols="30" rows="13">
Dear IT Service Request Team,

Please add:

<?= $person[2] ?> (<?= $person[0] ?>) <?= $person[3] ?>

to security group PSA_w_ELM_prod_C on Kepler.

Thank you,

The Corporate Learning Branch 
</textarea>

</div> <!-- /column --> 
</div> <!-- /row --> 
</div> <!-- /container --> 


<?php require('templates/javascript.php') ?>
<?php require('templates/footer.php') ?>
